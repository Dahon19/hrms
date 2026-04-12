<?php

namespace App\Http\Controllers;


use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentSubcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    /**
     * Display the Catalog of required documents.
     */
    public function index(Request $request)
    {
        Gate::authorize('view-documents');

        $search = $request->get('search');

        $documents = Document::query()
            ->when($search, function ($query) use ($search) {
                // Matching 'document' column based on your Blade input
                return $query->where('document', 'like', "%{$search}%");
            })
            ->with(['category', 'subcategory'])
            ->orderBy('document', 'asc')
            ->paginate(15)
            ->withQueryString();
        
        $employees = \App\Models\Employee::nonAdmin()->get();
        $categories = DocumentCategory::withCount('subcategories')
            ->orderByRaw('LOWER(name)')
            ->get();
        $subcategories = DocumentSubcategory::query()
            ->with('category')
            ->leftJoin('document_categories as categories', 'categories.id', '=', 'document_subcategories.document_category_id')
            ->select('document_subcategories.*')
            ->orderByRaw('LOWER(categories.name)')
            ->orderByRaw('LOWER(document_subcategories.name)')
            ->get();

        return view('documents.index', compact('documents', 'employees', 'categories', 'subcategories'));
    }

    /**
     * CATALOG ONLY: Store a new document type in the database.
     */
    public function store(Request $request)
    {
        Gate::authorize('manage-documents');

        $validated = $request->validate([
            // Matches 'name="document"' in your Blade
            'document' => 'required|string|max:255|unique:documents,document',
            'gender' => 'nullable|in:male,female',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'document_subcategory_id' => [
                'nullable',
                Rule::exists('document_subcategories', 'id')
                    ->where('document_category_id', $request->input('document_category_id')),
            ],
        ]);

        if (empty($validated['document_category_id'])) {
            $validated['document_subcategory_id'] = null;
        } elseif (!empty($validated['document_subcategory_id'])) {
            $isValidSubcategory = DocumentSubcategory::where('id', $validated['document_subcategory_id'])
                ->where('document_category_id', $validated['document_category_id'])
                ->exists();

            if (!$isValidSubcategory) {
                $validated['document_subcategory_id'] = null;
            }
        }
        try {
            Document::create($validated);
        } catch (QueryException $e) {
            // Graceful fallback when selected subcategory was deleted between page load and submit.
            if ((int) $e->getCode() === 23000) {
                $validated['document_subcategory_id'] = null;
                Document::create($validated);
            } else {
                throw $e;
            }
        }

        return redirect()->route('documents.index')
                         ->with('success', 'New document category added to catalog!');
    }

    public function update(Request $request, Document $document)
    {
        Gate::authorize('manage-documents');

        $validated = $request->validate([
            'document' => 'required|string|max:255|unique:documents,document,' . $document->id,
            'gender' => 'nullable|in:male,female',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'document_subcategory_id' => [
                'nullable',
                Rule::exists('document_subcategories', 'id')
                    ->where('document_category_id', $request->input('document_category_id')),
            ],
        ]);

        if (empty($validated['document_category_id'])) {
            $validated['document_subcategory_id'] = null;
        } elseif (!empty($validated['document_subcategory_id'])) {
            $isValidSubcategory = DocumentSubcategory::where('id', $validated['document_subcategory_id'])
                ->where('document_category_id', $validated['document_category_id'])
                ->exists();

            if (!$isValidSubcategory) {
                $validated['document_subcategory_id'] = null;
            }
        }
        try {
            $document->update($validated);
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                $validated['document_subcategory_id'] = null;
                $document->update($validated);
            } else {
                throw $e;
            }
        }

        return redirect()->route('documents.index')
                         ->with('success', 'Document type updated successfully!');
    }

    public function destroy(Document $document)
    {
        Gate::authorize('manage-documents');

        $document->delete();
        return redirect()->route('documents.index')
                         ->with('success', 'Document type removed from catalog!');
    }

    public function storeCategory(Request $request)
    {
        Gate::authorize('manage-documents');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:document_categories,name',
        ]);

        DocumentCategory::create([
            'name' => $validated['name'],
        ]);

        return redirect()->route('documents.index')
            ->with('success', 'Document category created.');
    }

    public function updateCategory(Request $request, DocumentCategory $category)
    {
        Gate::authorize('manage-documents');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('document_categories', 'name')->ignore($category->id)],
        ]);

        $category->update([
            'name' => $validated['name'],
        ]);

        return redirect()->route('documents.index')
            ->with('success', 'Document category updated.');
    }

    public function destroyCategory(DocumentCategory $category)
    {
        Gate::authorize('manage-documents');

        $category->delete();
        return redirect()->route('documents.index')
            ->with('success', 'Document category removed.');
    }

    public function storeSubcategory(Request $request)
    {
        Gate::authorize('manage-documents');

        $validated = $request->validate([
            'document_category_id' => 'required|exists:document_categories,id',
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('document_subcategories', 'name')
                    ->where('document_category_id', $request->input('document_category_id')),
            ],
        ]);

        DocumentSubcategory::create([
            'document_category_id' => $validated['document_category_id'],
            'name' => $validated['name'],
        ]);

        return redirect()->route('documents.index')
            ->with('success', 'Document subcategory created.');
    }

    public function updateSubcategory(Request $request, DocumentSubcategory $subcategory)
    {
        Gate::authorize('manage-documents');

        $validated = $request->validate([
            'document_category_id' => 'required|exists:document_categories,id',
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('document_subcategories', 'name')
                    ->where('document_category_id', $request->input('document_category_id'))
                    ->ignore($subcategory->id),
            ],
        ]);

        $subcategory->update([
            'document_category_id' => $validated['document_category_id'],
            'name' => $validated['name'],
        ]);

        return redirect()->route('documents.index')
            ->with('success', 'Document subcategory updated.');
    }

    public function destroySubcategory(DocumentSubcategory $subcategory)
    {
        Gate::authorize('manage-documents');

        $subcategory->delete();
        return redirect()->route('documents.index')
            ->with('success', 'Document subcategory removed.');
    }
}
