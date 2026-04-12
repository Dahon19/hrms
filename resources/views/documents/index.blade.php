@extends (request()->boolean('embedded') ? 'layouts.embedded' : 'layouts.admin')
@section ('content')
    @php use App\Support\FormValidation; @endphp
    <div
        class="container-fluid documents-workspace pb-4"
        id="documentsIndexPage"
        data-has-errors="{{ $errors->any() ? '1' : '0' }}"
        data-form-context="{{ old('form_context') }}"
        data-document-error="{{ old('form_context') === 'employee_doc_upload' ? '1' : '0' }}"
        data-has-employee="{{ request('employee_id') ? '1' : '0' }}"
        data-default-view="{{ auth()->user()->isAdmin() ? (request('employee_id') ? 'employee' : 'catalog') : 'employee' }}"
    >
        @php $privateUrl = function (?string $path) { if (!$path) { return null; } $parts = explode('/', $path); if (count($parts) < 3) { return null; } return route('storage.file', [ 'folder' => $parts[0], 'subfolder' => $parts[1], 'filename' => $parts[2], ]); }; @endphp
        @php $isEmbedded = request()->boolean('embedded'); $isEmployeeRoute = request()->routeIs('employee-documents.index') || request()->is('employee-documents*'); $isCatalogRoute = request()->routeIs('documents.index') || request()->is('documents*'); $pageTitle = $isCatalogRoute ? 'Document Catalog' : 'Employee Documents'; $pageSubtitle = $isCatalogRoute ? 'Manage document categories and requirements.' : 'Manage employee compliance documents.'; @endphp
        @php $embeddedActionParams = $isEmbedded ? ['embedded' => 1] : []; @endphp
        @unless (request()->boolean('embedded'))
            <x-page-header
                eyebrow="Operations"
                :title="$pageTitle"
                :subtitle="$pageSubtitle"
                class="doc-page-header"
            >
                @if ($isCatalogRoute && auth()->user()->isAdmin())
                    <x-slot:actions>
                        <div class="hero-actions__row document-hero-actions">
                            <div class="dropdown document-catalog-dropdown">
                                <x-ui.button
                                    variant="outline-secondary"
                                    class="dropdown-toggle"
                                    type="button"
                                    id="documentCatalogDropdown"
                                    data-toggle="dropdown"
                                    aria-haspopup="true"
                                    aria-expanded="false"
                                    icon="cil-library-building"
                                >
                                    <span>Categories</span>
                                </x-ui.button>
                                <div
                                    class="dropdown-menu dropdown-menu-right shadow-sm"
                                    aria-labelledby="documentCatalogDropdown"
                                >
                                    <a
                                        class="dropdown-item active"
                                        id="doc-categories-tab"
                                        data-toggle="tab"
                                        href="#doc-categories"
                                        role="tab"
                                        aria-controls="doc-categories"
                                        aria-selected="true"
                                    >
                                        <i class="cil-sitemap mr-2"></i>Categories
                                    </a>
                                    <a
                                        class="dropdown-item"
                                        id="doc-subcategories-tab"
                                        data-toggle="tab"
                                        href="#doc-subcategories"
                                        role="tab"
                                        aria-controls="doc-subcategories"
                                        aria-selected="false"
                                    >
                                        <i class="cil-folder-open mr-2"></i>Subcategories
                                    </a>
                                    <a
                                        class="dropdown-item"
                                        id="doc-documents-tab"
                                        data-toggle="tab"
                                        href="#doc-documents"
                                        role="tab"
                                        aria-controls="doc-documents"
                                        aria-selected="false"
                                    >
                                        <i class="cil-description mr-2"></i>Document Types
                                    </a>
                                </div>
                            </div>
                            <x-ui.button
                                type="button"
                                variant="primary"
                                id="documentCatalogCreateAction"
                                data-toggle="modal"
                                data-target="#categoryCreateModal"
                                data-coreui-target="#categoryCreateModal"
                                icon="cil-plus"
                            >
                                <span>Create</span>
                            </x-ui.button>
                        </div>
                    </x-slot:actions>
                @endif
            </x-page-header>
        @endunless
        @if ((!$isCatalogRoute || !auth()->user()->isAdmin()) && isset($selectedEmployee) && isset($documentGroups))
            @php $allDocumentsForSummary = collect($documentGroups)->flatMap(function ($subcategoryGroups) { return collect($subcategoryGroups)->flatMap(function ($docs) { return $docs; }); }); $totalRequirements = $allDocumentsForSummary->count(); $uploadedRequirements = $allDocumentsForSummary->whereNotNull('upload_id')->count(); $verifiedRequirements = $allDocumentsForSummary->where('status_raw', 'verified')->count(); $expiringRequirements = $allDocumentsForSummary->whereIn('expiry_state', ['expiring', 'expired'])->count(); $missingRequirements = $allDocumentsForSummary->whereNull('upload_id')->count(); @endphp
            <div class="doc-summary-strip mb-4">
                <div class="doc-summary-chip">
                    <span class="doc-summary-label">Total Requirements</span>
                    <span
                        class="doc-summary-value"
                        >{{ $totalRequirements }}</span
                    >
                </div>
                <div class="doc-summary-chip">
                    <span class="doc-summary-label">Uploaded</span>
                    <span
                        class="doc-summary-value"
                        >{{ $uploadedRequirements }}</span
                    >
                </div>
                <div class="doc-summary-chip is-verified">
                    <span class="doc-summary-label">Verified</span>
                    <span
                        class="doc-summary-value"
                        >{{ $verifiedRequirements }}</span
                    >
                </div>
                <div class="doc-summary-chip is-expiring">
                    <span class="doc-summary-label">Expiring</span>
                    <span
                        class="doc-summary-value"
                        >{{ $expiringRequirements }}</span
                    >
                </div>
                <div class="doc-summary-chip is-missing">
                    <span class="doc-summary-label">Missing</span>
                    <span
                        class="doc-summary-value"
                        >{{ $missingRequirements }}</span
                    >
                </div>
            </div>
        @endif
        @if (!$isCatalogRoute || !auth()->user()->isAdmin())
            <div id="employeeDocumentsSection">
                {{-- ===================== 2. DOSSIER CONTENT ===================== --}}
            @if (isset($selectedEmployee))
                @php
                    $currentEmployeeId = auth()->user()->employee->id ?? null;
                    $isOwnDocumentsView = $currentEmployeeId && (int) $currentEmployeeId === (int) $selectedEmployee->id;
                    $canManageEmployeeDocs = auth()->user()->isAdmin() || $isOwnDocumentsView;
                @endphp
                <div class="card mb-4 border-0 hrms-list-card document-directory-card">
                    @unless ($isEmbedded || $isOwnDocumentsView)
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex align-items-center flex-wrap">
                            <h3
                                class="card-title font-weight-bold mb-2 mb-md-0"
                            >
                                {{ $selectedEmployee->first_name }} {{ $selectedEmployee->last_name }}
                                <span
                                    class="ml-2 badge badge-primary font-weight-normal"
                                    >#{{ $selectedEmployee->employee_id }}</span
                                >
                            </h3>
                        </div>
                    </div>
                    @endunless
                    <div class="card-body p-4">
                        @if ($documentGroups->isEmpty())
                            <div class="text-center py-5">
                                <i class="cil-folder fs-2 text-muted mb-3"></i>
                                <p class="text-muted mb-0">No document categories configured for this employee.</p>
                            </div>
                        @else
                            <div
                                id="documentsAccordion"
                                class="accordion-custom"
                            >
                                @foreach ($documentGroups as $categoryName => $categoryGroups)
                                    @php $categorySlug = 'cat-' . \Illuminate\Support\Str::slug($categoryName) . '-' . $loop->index; $categoryDocumentCount = collect($categoryGroups)->sum(function ($docs) { return collect($docs)->count(); }); @endphp
                                    <div
                                        class="card mb-3 border border-light doc-folder-card"
                                    >
                                        <div
                                            class="card-header p-0 doc-folder-header"
                                            id="heading-{{ $categorySlug }}"
                                        >
                                            <button
                                                class="btn w-100 text-left px-4 py-3 font-weight-bold text-dark d-flex justify-content-between align-items-center doc-folder-toggle"
                                                type="button"
                                                data-toggle="collapse"
                                                data-target="#{{ $categorySlug }}"
                                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                            >
                                                <span
                                                    class="d-inline-flex align-items-center"
                                                >
                                                    <i
                                                        class="cil-folder text-warning mr-2"
                                                    ></i>
                                                    {{ $categoryName }}
                                                    <span
                                                        class="badge badge-pill ml-2 doc-folder-count"
                                                        >{{ $categoryDocumentCount }}</span
                                                    >
                                                </span>
                                                <i
                                                    class="cil-chevron-bottom accordion-arrow"
                                                ></i>
                                            </button>
                                        </div>
                                        <div
                                            id="{{ $categorySlug }}"
                                            class="collapse {{ $loop->first ? 'show' : '' }}"
                                            data-parent="#documentsAccordion"
                                        >
                                            <div
                                                class="card-body px-4 pb-4 pt-2"
                                            >
                                                @foreach ($categoryGroups as $subcategoryName => $docs)
                                                    <div
                                                        class="mt-3 mb-4 doc-subsection-block"
                                                    >
                                                        <h6
                                                            class="text-primary small font-weight-bold text-uppercase mb-3 ps-2 doc-subsection-title"
                                                        >
                                                            {{ $subcategoryName }}
                                                        </h6>
                                                        <div class="doc-rows">
                                                            @foreach ($docs as $doc)
                                                                <div
                                                                    class="doc-row-item"
                                                                >
                                                                    <div
                                                                        class="doc-row-main"
                                                                    >
                                                                        <div
                                                                            class="doc-row-icon"
                                                                        >
                                                                            <i
                                                                                class="cil-description"
                                                                            ></i>
                                                                        </div>
                                                                        <div
                                                                            class="doc-row-text"
                                                                        >
                                                                            <span
                                                                                class="doc-row-name"
                                                                                >{{ $doc->document_name }}</span
                                                                            >
                                                                        </div>
                                                                    </div>
                                                                    <div
                                                                        class="doc-row-status"
                                                                    >
                                                                        <span
                                                                            class="badge doc-status-badge {{ $doc->badge_class }}"
                                                                            >{{ $doc->status }}</span
                                                                        >
                                                                        @if ($doc->expiry_state === 'expired')
                                                                            <span
                                                                                class="badge doc-status-badge badge-danger"
                                                                                >Expired</span
                                                                            >
                                                                        @elseif ($doc->expiry_state === 'expiring')
                                                                            <span
                                                                                class="badge doc-status-badge badge-warning text-dark"
                                                                                >Expiring
                                                                                Soon</span
                                                                            >
                                                                        @endif
                                                                        @if ($doc->expires_display)
                                                                            <small
                                                                                class="doc-expiry-text"
                                                                                >Expires {{ $doc->expires_display }}</small
                                                                            >
                                                                        @endif
                                                                        @if ($doc->status_raw === 'reupload' && $doc->review_notes)
                                                                            <small
                                                                                class="doc-review-note"
                                                                                >{{ $doc->review_notes }}</small
                                                                            >
                                                                        @endif
                                                                    </div>
                                                                    <div
                                                                        class="doc-row-actions"
                                                                    >
                                                                        @if ($doc->upload_id)
                                                                            @php $fileUrl = $privateUrl($doc->file_path); @endphp
                                                                            <div
                                                                                class="crud-actions justify-content-center"
                                                                            >
                                                                                @if ($canReviewDocuments)
                                                                                    @if ($fileUrl)
                                                                                        <x-ui.button
                                                                                            variant="view"
                                                                                            size="sm"
                                                                                            :href="$fileUrl"
                                                                                            target="_blank"
                                                                                            rel="noopener"
                                                                                            icon="cil-fullscreen"
                                                                                            aria-label="Open {{ $doc->document_name }}"
                                                                                            title="Open {{ $doc->document_name }}"
                                                                                        />
                                                                                    @endif
                                                                                    @if ($doc->status_raw !== 'verified')
                                                                                        <form
                                                                                            action="{{ route('employee-documents.verify', array_merge(['employeeDocument' => $doc->upload_id], $embeddedActionParams)) }}"
                                                                                            method="POST"
                                                                                            class="d-inline"
                                                                                            data-document="{{ $doc->document_name }}"
                                                                                        >
                                                                                            @csrf
                                                                                            @if ($isEmbedded)
                                                                                                <input type="hidden" name="embedded" value="1" />
                                                                                            @endif
                                                                                            <x-ui.button
                                                                                                type="submit"
                                                                                                variant="approve"
                                                                                                size="sm"
                                                                                            />
                                                                                        </form>
                                                                                        <x-ui.button
                                                                                            type="reupload"
                                                                                            size="sm"
                                                                                            class="request-reupload"
                                                                                            data-toggle="modal"
                                                                                            data-target="#documentReuploadModal"
                                                                                            data-action="{{ route('employee-documents.reupload', array_merge(['employeeDocument' => $doc->upload_id], $embeddedActionParams)) }}"
                                                                                            data-document="{{ $doc->document_name }}"
                                                                                            data-current-notes="{{ $doc->review_notes ?? '' }}"
                                                                                        />
                                                                                        @if ($doc->status_raw === 'reupload')
                                                                                            <form
                                                                                                action="{{ route('employee-documents.remind-reupload', array_merge(['employeeDocument' => $doc->upload_id], $embeddedActionParams)) }}"
                                                                                                method="POST"
                                                                                                class="d-inline"
                                                                                                data-confirm-message="Send a reupload reminder for {{ $doc->document_name }}?"
                                                                                                data-confirm-title="Send Reupload Reminder"
                                                                                                data-confirm-label="Send Reminder"
                                                                                                data-confirm-variant="warning"
                                                                                            >
                                                                                                @csrf
                                                                                                @if ($isEmbedded)
                                                                                                    <input type="hidden" name="embedded" value="1" />
                                                                                                @endif
                                                                                                <x-ui.button
                                                                                                    type="remind"
                                                                                                    size="sm"
                                                                                                    aria-label="Send Reupload Reminder"
                                                                                                    title="Send Reupload Reminder"
                                                                                                />
                                                                                            </form>
                                                                                        @endif
                                                                                    @endif
                                                                                @endif
                                                                                @if ($canReviewDocuments && in_array($doc->expiry_state, ['expired', 'expiring'], true))
                                                                                    <form
                                                                                        action="{{ route('employee-documents.remind-expiry', array_merge(['employeeDocument' => $doc->upload_id], $embeddedActionParams)) }}"
                                                                                        method="POST"
                                                                                        class="d-inline"
                                                                                        data-confirm-message="Send an expiry reminder for {{ $doc->document_name }}?"
                                                                                        data-confirm-title="Send Expiry Reminder"
                                                                                        data-confirm-label="Send Reminder"
                                                                                        data-confirm-variant="info"
                                                                                    >
                                                                                        @csrf
                                                                                        @if ($isEmbedded)
                                                                                            <input type="hidden" name="embedded" value="1" />
                                                                                        @endif
                                                                                        <x-ui.button
                                                                                            type="remind"
                                                                                            size="sm"
                                                                                            aria-label="Send Expiry Reminder"
                                                                                            title="Send Expiry Reminder"
                                                                                        />
                                                                                    </form>
                                                                                @endif
                                                                                @if (!$isEmbedded && !$canReviewDocuments && $fileUrl)
                                                                                    <x-ui.button
                                                                                        type="view"
                                                                                        size="sm"
                                                                                        data-toggle="modal"
                                                                                        data-target="#documentPreviewModal"
                                                                                        data-file="{{ $fileUrl }}"
                                                                                        data-title="{{ $doc->document_name }}"
                                                                                    />
                                                                                @endif
                                                                                @if ($canManageEmployeeDocs && !$isEmbedded)
                                                                                    @php $employeeDocumentEditPayload = [ 'update_url' => route('employee-documents.update', $doc->upload_id), 'document_name' => $doc->document_name, 'file_url' => $fileUrl ?? '', 'review_notes' => $doc->review_notes ?? '', 'issued' => $doc->issued_at ?? '', 'expires' => $doc->expires_at ?? '', ]; @endphp
                                                                                    @php if ($isEmbedded) { $employeeDocumentEditPayload['update_url'] = route('employee-documents.update', ['employeeDocument' => $doc->upload_id, 'embedded' => 1]); } @endphp
                                                                                    <x-ui.button
                                                                                        type="edit"
                                                                                        size="sm"
                                                                                        class="edit-employee-document"
                                                                                        data-toggle="modal"
                                                                                        data-target="#employeeDocumentEditModal"
                                                        data-edit="{{ json_encode($employeeDocumentEditPayload) }}"
                                                                                    />
                                                                                @endif
                                                                                @if (auth()->user()->isAdmin())
                                                                                    <form
                                                                                        action="{{ route('employee-documents.destroy', array_merge(['employeeDocument' => $doc->upload_id], $embeddedActionParams)) }}"
                                                                                        method="POST"
                                                                                        class="d-inline"
                                                                                        data-confirm-message="Delete {{ $doc->document_name }} for {{ $selectedEmployee->first_name }} {{ $selectedEmployee->last_name }}?"
                                                                                        data-confirm-title="Delete Employee Document"
                                                                                        data-confirm-label="Delete"
                                                                                        data-confirm-variant="danger"
                                                                                    >
                                                                                        @csrf
                                                                                        @method ('DELETE')
                                                                                        @if ($isEmbedded)
                                                                                            <input type="hidden" name="embedded" value="1" />
                                                                                        @endif
                                                                                        <x-ui.button
                                                                                            type="submit"
                                                                                            variant="delete"
                                                                                            size="sm"
                                                                                            aria-label="Delete Employee Document"
                                                                                            title="Delete Employee Document"
                                                                                        />
                                                                                    </form>
                                                                                @endif
                                                                            </div>
                                                                        @else
                                                                            @if ($canManageEmployeeDocs && !$isEmbedded)
                                                                                <div
                                                                                    class="crud-actions justify-content-center"
                                                                                >
                                                                                    <x-ui.button
                                                                                        type="upload"
                                                                                        size="sm"
                                                                                        data-toggle="modal"
                                                                                        data-target="#documentUploadModal"
                                                                                        data-employee-id="{{ $doc->employee_id }}"
                                                                                        data-employee-name="{{ $selectedEmployee->first_name }} {{ $selectedEmployee->last_name }}"
                                                                                        data-document-id="{{ $doc->document_id }}"
                                                                                        data-document-name="{{ $doc->document_name }}"
                                                                                        aria-label="Upload {{ $doc->document_name }}"
                                                                                        title="Upload {{ $doc->document_name }}"
                                                                                    />
                                                                                </div>
                                                                            @else
                                                                                <span
                                                                                    class="text-muted small"
                                                                                    >Not
                                                                                    uploaded</span
                                                                                >
                                                                            @endif
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
            </div>
        @endif
    <x-modal
        id="documentPreviewModal"
        size="xl"
        title="Document Preview"
        title-id="documentPreviewModalLabel"
        header-class="bg-light"
        aria-labelledby="documentPreviewModalLabel"
    >
                <div class="modal-body p-0">
                    <iframe
                        title="Document Preview"
                        src="about:blank"
                        loading="lazy"
                        style="width: 100%; height: 80vh; border: 0"
                    ></iframe>
                </div>
    </x-modal>
    @if (auth()->user()->role === 'admin')
        <x-modal id="categoryCreateModal" size="lg" title="Add Category">
                    <form
                        action="{{ route('documents.categories.store') }}"
                        method="POST"
                    >
                        @csrf
                        <input
                            type="hidden"
                            name="form_context"
                            value="category_create"
                        />
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="category_create_name"
                                    >Category Name
                                    <span class="text-danger">*</span></label
                                >
                                <input
                                    type="text"
                                    id="category_create_name"
                                    name="name"
                                    class="form-control {{ FormValidation::invalidClass('name', 'category_create') }}"
                                    placeholder="e.g. Government Documents"
                                    value="{{ old('name') }}"
                                    required
                                />
                                <x-ui.form-error field="name" context="category_create" />
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <x-ui.button
                                type="submit"
                                variant="primary"
                                size="sm"
                                icon="cil-save"
                            >
                                Save
                            </x-ui.button>
                        </x-ui.modal-footer>
                    </form>
        </x-modal>
        <x-modal id="categoryEditModal" size="lg" title="Edit Category">
                    <form id="categoryEditForm" action="#" method="POST">
                        @csrf
                        @method ('PATCH')
                        <input
                            type="hidden"
                            name="form_context"
                            value="category_edit"
                        />
                        <input
                            type="hidden"
                            name="update_url"
                            id="category_edit_url"
                            value="{{ old('update_url') }}"
                        />
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="category_edit_name"
                                    >Category Name
                                    <span class="text-danger">*</span></label
                                >
                                <input
                                    type="text"
                                    id="category_edit_name"
                                    name="name"
                                    class="form-control {{ FormValidation::invalidClass('name', 'category_edit') }}"
                                    value="{{ old('name') }}"
                                    required
                                />
                                <x-ui.form-error field="name" context="category_edit" />
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <x-ui.button
                                type="submit"
                                variant="primary"
                                size="sm"
                                icon="cil-save"
                            >
                                Save</x-ui.button
                            >
                        </x-ui.modal-footer>
                    </form>
        </x-modal>
        <x-modal
            id="subcategoryCreateModal"
            size="lg"
            title="Add Subcategory"
            title-id="subcategoryCreateModalLabel"
        >
                    <form
                        action="{{ route('documents.subcategories.store') }}"
                        method="POST"
                    >
                        @csrf
                        <input
                            type="hidden"
                            name="form_context"
                            value="subcategory_create"
                        />
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="subcategory_create_category"
                                    >Category
                                    <span class="text-danger">*</span></label
                                >
                                <select
                                    name="document_category_id"
                                    id="subcategory_create_category"
                                    class="form-control select2bs4 {{ FormValidation::invalidClass('document_category_id', 'subcategory_create') }}"
                                    data-placeholder="Select category"
                                    required
                                >
                                    <option value="">Select category</option>
                                    @foreach ($categories as $category)
                                        <option
                                            value="{{ $category->id }}"
                                            {{ old('document_category_id') == $category->id ? 'selected' : '' }}
                                        >
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-ui.form-error field="document_category_id" context="subcategory_create" />
                            </div>
                            <div class="form-group">
                                <label for="subcategory_create_name"
                                    >Subcategory Name
                                    <span class="text-danger">*</span></label
                                >
                                <input
                                    type="text"
                                    id="subcategory_create_name"
                                    name="name"
                                    class="form-control {{ FormValidation::invalidClass('name', 'subcategory_create') }}"
                                    placeholder="e.g. Employment"
                                    value="{{ old('name') }}"
                                    required
                                />
                                <x-ui.form-error field="name" context="subcategory_create" />
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <x-ui.button
                                type="submit"
                                variant="primary"
                                size="sm"
                                icon="cil-save"
                            >
                                Save
                            </x-ui.button>
                        </x-ui.modal-footer>
                    </form>
        </x-modal>
        <x-modal
            id="subcategoryEditModal"
            size="lg"
            title="Edit Subcategory"
            title-id="subcategoryEditModalLabel"
        >
                    <form id="subcategoryEditForm" action="#" method="POST">
                        @csrf
                        @method ('PATCH')
                        <input
                            type="hidden"
                            name="form_context"
                            value="subcategory_edit"
                        />
                        <input
                            type="hidden"
                            name="update_url"
                            id="subcategory_edit_url"
                            value="{{ old('update_url') }}"
                        />
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="subcategory_edit_category"
                                    >Category
                                    <span class="text-danger">*</span></label
                                >
                                <select
                                    name="document_category_id"
                                    id="subcategory_edit_category"
                                    class="form-control select2bs4 {{ FormValidation::invalidClass('document_category_id', 'subcategory_edit') }}"
                                    data-placeholder="Select category"
                                    required
                                >
                                    <option value="">Select category</option>
                                    @foreach ($categories as $category)
                                        <option
                                            value="{{ $category->id }}"
                                            {{ old('document_category_id') == $category->id ? 'selected' : '' }}
                                        >
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-ui.form-error field="document_category_id" context="subcategory_edit" />
                            </div>
                            <div class="form-group">
                                <label for="subcategory_edit_name"
                                    >Subcategory Name
                                    <span class="text-danger">*</span></label
                                >
                                <input
                                    type="text"
                                    id="subcategory_edit_name"
                                    name="name"
                                    class="form-control {{ FormValidation::invalidClass('name', 'subcategory_edit') }}"
                                    value="{{ old('name') }}"
                                    required
                                />
                                <x-ui.form-error field="name" context="subcategory_edit" />
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <x-ui.button
                                type="submit"
                                variant="primary"
                                size="sm"
                                icon="cil-save"
                            >
                                Save</x-ui.button
                            >
                        </x-ui.modal-footer>
                    </form>
        </x-modal>
        <x-modal
            id="catalogCreateModal"
            size="lg"
            title="Add Document Type"
            icon="cil-plus"
        >
                    <form action="{{ route('documents.store') }}" method="POST">
                        @csrf
                        <input
                            type="hidden"
                            name="form_context"
                            value="catalog_create"
                        />
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="catalog_create_document_name"
                                    >Document Title/Name
                                    <span class="text-danger">*</span></label
                                >
                                <input
                                    type="text"
                                    id="catalog_create_document_name"
                                    name="document"
                                    class="form-control {{ FormValidation::invalidClass('document', 'catalog_create') }}"
                                    placeholder="e.g. NBI Clearance, Health Certificate"
                                    required
                                    value="{{ old('document') }}"
                                />
                                <x-ui.form-error field="document" context="catalog_create" />
                            </div>
                            <div class="form-group">
                                <label>Gender Restriction</label>
                                <input
                                    type="hidden"
                                    id="catalog_create_gender"
                                    name="gender"
                                    value="{{ old('gender', '') }}"
                                />
                                <div
                                    class="doc-gender-checks"
                                    data-gender-target="#catalog_create_gender"
                                >
                                    <label class="doc-gender-check">
                                        <input
                                            type="checkbox"
                                            value="male"
                                            {{ old('gender') === 'male' ? 'checked' : '' }}
                                        />
                                        <span>Male Only</span>
                                    </label>
                                    <label class="doc-gender-check">
                                        <input
                                            type="checkbox"
                                            value="female"
                                            {{ old('gender') === 'female' ? 'checked' : '' }}
                                        />
                                        <span>Female Only</span>
                                    </label>
                                </div>
                                <small class="text-muted"
                                    >Optional. Restrict this document to a
                                    specific gender.</small
                                >
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="catalog_create_category"
                                        >Category</label
                                    >
                                    <select
                                        name="document_category_id"
                                        id="catalog_create_category"
                                        class="form-control select2bs4 document-category-select {{ FormValidation::invalidClass('document_category_id', 'catalog_create') }}"
                                        data-placeholder="Select category"
                                        data-target="#catalog_create_subcategory"
                                    >
                                        <option value="">-- None --</option>
                                        @foreach ($categories as $category)
                                            <option
                                                value="{{ $category->id }}"
                                                {{ old('document_category_id') == $category->id ? 'selected' : '' }}
                                            >
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-ui.form-error field="document_category_id" context="catalog_create" />
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="catalog_create_subcategory"
                                        >Subcategory</label
                                    >
                                    <select
                                        name="document_subcategory_id"
                                        id="catalog_create_subcategory"
                                        class="form-control select2bs4 document-subcategory-select {{ FormValidation::invalidClass('document_subcategory_id', 'catalog_create') }}"
                                        data-placeholder="Select subcategory"
                                        data-category-source="#catalog_create_category"
                                    >
                                        <option value="">-- None --</option>
                                        @foreach ($subcategories as $subcategory)
                                            <option
                                                value="{{ $subcategory->id }}"
                                                data-category="{{ $subcategory->document_category_id }}"
                                                {{ old('document_subcategory_id') == $subcategory->id ? 'selected' : '' }}
                                            >
                                                {{ $subcategory->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-ui.form-error field="document_subcategory_id" context="catalog_create" />
                                </div>
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <x-ui.button
                                type="submit"
                                variant="primary"
                                size="sm"
                                icon="cil-save"
                            >
                                Save
                            </x-ui.button>
                        </x-ui.modal-footer>
                    </form>
                </div>
            </div>
        </x-modal>
        <x-modal
            id="catalogEditModal"
            size="lg"
            title="Edit Document Type"
            icon="cil-pencil"
        >
                    <form id="catalogEditForm" action="#" method="POST">
                        @csrf
                        @method ('PUT')
                        <input
                            type="hidden"
                            name="form_context"
                            value="catalog_edit"
                        />
                        <input
                            type="hidden"
                            name="update_url"
                            id="catalog_edit_url"
                            value="{{ old('update_url') }}"
                        />
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="catalog_edit_name"
                                    >Document Title/Name
                                    <span class="text-danger">*</span></label
                                >
                                <input
                                    type="text"
                                    id="catalog_edit_name"
                                    name="document"
                                    class="form-control {{ FormValidation::invalidClass('document', 'catalog_edit') }}"
                                    value="{{ old('document') }}"
                                    required
                                />
                                <x-ui.form-error field="document" context="catalog_edit" />
                            </div>
                            <div class="form-group">
                                <label>Gender Restriction</label>
                                <input
                                    type="hidden"
                                    id="catalog_edit_gender"
                                    name="gender"
                                    value="{{ old('gender', '') }}"
                                />
                                <div
                                    class="doc-gender-checks"
                                    data-gender-target="#catalog_edit_gender"
                                >
                                    <label class="doc-gender-check">
                                        <input
                                            type="checkbox"
                                            value="male"
                                            {{ old('gender') === 'male' ? 'checked' : '' }}
                                        />
                                        <span>Male Only</span>
                                    </label>
                                    <label class="doc-gender-check">
                                        <input
                                            type="checkbox"
                                            value="female"
                                            {{ old('gender') === 'female' ? 'checked' : '' }}
                                        />
                                        <span>Female Only</span>
                                    </label>
                                </div>
                                <small class="text-muted"
                                    >Optional. Restrict this document to a
                                    specific gender.</small
                                >
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="catalog_edit_category"
                                        >Category</label
                                    >
                                    <select
                                        name="document_category_id"
                                        id="catalog_edit_category"
                                        class="form-control select2bs4 document-category-select {{ FormValidation::invalidClass('document_category_id', 'catalog_edit') }}"
                                        data-placeholder="Select category"
                                        data-target="#catalog_edit_subcategory"
                                    >
                                        <option value="">-- None --</option>
                                        @foreach ($categories as $category)
                                            <option
                                                value="{{ $category->id }}"
                                                {{ old('document_category_id') == $category->id ? 'selected' : '' }}
                                            >
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-ui.form-error field="document_category_id" context="catalog_edit" />
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="catalog_edit_subcategory"
                                        >Subcategory</label
                                    >
                                    <select
                                        name="document_subcategory_id"
                                        id="catalog_edit_subcategory"
                                        class="form-control select2bs4 document-subcategory-select {{ FormValidation::invalidClass('document_subcategory_id', 'catalog_edit') }}"
                                        data-placeholder="Select subcategory"
                                        data-category-source="#catalog_edit_category"
                                    >
                                        <option value="">-- None --</option>
                                        @foreach ($subcategories as $subcategory)
                                            <option
                                                value="{{ $subcategory->id }}"
                                                data-category="{{ $subcategory->document_category_id }}"
                                                {{ old('document_subcategory_id') == $subcategory->id ? 'selected' : '' }}
                                            >
                                                {{ $subcategory->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-ui.form-error field="document_subcategory_id" context="catalog_edit" />
                                </div>
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <x-ui.button
                                type="submit"
                                variant="primary"
                                size="sm"
                                icon="cil-save"
                            >
                                Save</x-ui.button
                            >
                        </x-ui.modal-footer>
                    </form>
        </x-modal>
    @endif
    <x-modal id="employeeDocumentEditModal" size="lg" title="Update File">
                <form
                    id="employeeDocumentEditForm"
                    action="#"
                    method="POST"
                    enctype="multipart/form-data"
                >
                    @csrf
                    @method ('PATCH')
                    <input
                        type="hidden"
                        name="form_context"
                        value="employee_doc_edit"
                    />
                    <input
                        type="hidden"
                        name="update_url"
                        id="employee_doc_edit_url"
                        value="{{ old('update_url') }}"
                    />
                    <input
                        type="hidden"
                        name="document_name"
                        id="employee_doc_edit_document_name"
                        value="{{ old('document_name') }}"
                    />
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="font-weight-bold">Document</label>
                            <div
                                class="p-3 border rounded bg-light d-flex justify-content-between align-items-center"
                            >
                                <div class="text-truncate mr-3">
                                    <i
                                        class="cil-paperclip text-muted mr-2"
                                    ></i>
                                    <span
                                        class="font-weight-bold text-info"
                                        id="employee-doc-edit-name"
                                        >Document</span
                                    >
                                </div>
                                <x-ui.button
                                    type="view"
                                    size="sm"
                                    variant="info"
                                    data-toggle="modal"
                                    data-target="#documentPreviewModal"
                                    id="employee-doc-edit-preview"
                                    data-file=""
                                    data-title=""
                                    icon="cil-eye"
                                >
                                    View Current
                                </x-ui.button>
                            </div>
                        </div>
                        <div
                            class="alert alert-warning d-none"
                            id="employee-doc-edit-review-notes-wrap"
                        >
                            <div class="d-flex align-items-start">
                                <i class="cil-warning mr-2 mt-1"></i>
                                <div>
                                    <div class="font-weight-bold">
                                        Reupload Feedback
                                    </div>
                                    <p class="mb-0" id="employee-doc-edit-review-notes"></p>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="employee_doc_edit_file"
                                >Replace with New File</label
                            >
                            <input
                                type="file"
                                name="file"
                                id="employee_doc_edit_file"
                                class="filepond {{ FormValidation::invalidClass('file', 'employee_doc_edit') }}"
                                data-accepted-file-types=".pdf,.jpg,.jpeg,.png"
                                data-max-file-size="5MB"
                            />
                            <x-ui.form-error field="file" context="employee_doc_edit" />
                            <small class="text-muted mt-2"
                                >Allowed: PDF, JPG, PNG (Max 5MB).</small
                            >
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="employee_doc_edit_issued_at">
                                    Issuing Date
                                    <span
                                        class="text-danger d-none"
                                        id="employee_doc_edit_issued_required"
                                        >*</span
                                    >
                                </label>
                                <input
                                    type="date"
                                    name="issued_at"
                                    id="employee_doc_edit_issued_at"
                                    class="form-control {{ FormValidation::invalidClass('issued_at', 'employee_doc_edit') }}"
                                    value="{{ old('issued_at') }}"
                                />
                                <x-ui.form-error field="issued_at" context="employee_doc_edit" />
                                <small
                                    class="text-muted mt-2"
                                    id="employee_doc_edit_issued_note"
                                >
                                    Optional. Leave blank if not applicable.
                                </small>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="employee_doc_edit_expires_at">
                                    Expiration Date
                                    <span
                                        class="text-danger d-none"
                                        id="employee_doc_edit_expires_required"
                                        >*</span
                                    >
                                </label>
                                <input
                                    type="date"
                                    name="expires_at"
                                    id="employee_doc_edit_expires_at"
                                    class="form-control {{ FormValidation::invalidClass('expires_at', 'employee_doc_edit') }}"
                                    value="{{ old('expires_at') }}"
                                />
                                <x-ui.form-error field="expires_at" context="employee_doc_edit" />
                                <small
                                    class="text-muted mt-2"
                                    id="employee_doc_edit_expires_note"
                                >
                                    Optional. Leave blank if not applicable.
                                </small>
                            </div>
                        </div>
                    </div>
                    <x-ui.modal-footer>
                        <x-ui.button
                            type="submit"
                            variant="primary"
                            size="sm"
                            icon="cil-save"
                        >
                            Save</x-ui.button
                        >
                    </x-ui.modal-footer>
                </form>
    </x-modal>
    <x-modal
        id="documentReuploadModal"
        size="lg"
        title="Request Reupload"
        icon="cil-action-undo"
    >
                <form id="documentReuploadForm" action="#" method="POST">
                    @csrf
                    <input type="hidden" name="form_context" value="document_reupload" />
                    <input
                        type="hidden"
                        name="action_url"
                        id="document_reupload_action_url"
                        value="{{ old('action_url') }}"
                    />
                    @if ($isEmbedded)
                        <input type="hidden" name="embedded" value="1" />
                    @endif
                    <div class="modal-body">
                        <div class="p-3 border rounded bg-light mb-3">
                            <div class="text-muted small text-uppercase">
                                Document
                            </div>
                            <div
                                class="font-weight-bold text-info"
                                id="documentReuploadName"
                            >
                                Document
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label for="document_reupload_notes"
                                >Reason for Reupload</label
                            >
                            <textarea
                                name="review_notes"
                                id="document_reupload_notes"
                                rows="4"
                                class="form-control {{ FormValidation::invalidClass('review_notes', 'document_reupload') }}"
                                placeholder="Explain what needs to be corrected or updated."
                                required
                            >{{ old('form_context') === 'document_reupload' ? old('review_notes') : '' }}</textarea>
                            <x-ui.form-error field="review_notes" context="document_reupload" />
                            <small class="text-muted mt-2 d-block"
                                >These notes will be shown to the
                                employee.</small
                            >
                        </div>
                    </div>
                    <x-ui.modal-footer>
                        <x-ui.button
                            type="submit"
                            variant="warning"
                            size="sm"
                            class="text-white"
                            icon="cil-paper-plane"
                        >
                            Send Request
                        </x-ui.button>
                    </x-ui.modal-footer>
                </form>
    </x-modal>
    <x-modal
        id="documentUploadModal"
        size="lg"
        title="Upload Document"
        icon="cil-cloud-upload"
    >
                <form
                    id="documentUploadForm"
                    action="{{ route('employee-documents.store', $embeddedActionParams) }}"
                    method="POST"
                    enctype="multipart/form-data"
                >
                    @csrf
                    <input type="hidden" name="form_context" value="employee_doc_upload" />
                    @if ($isEmbedded)
                        <input type="hidden" name="embedded" value="1" />
                    @endif
                    <div class="modal-body">
                        <input
                            type="hidden"
                            name="employee_id"
                            id="upload_employee_id"
                            value="{{ old('employee_id') }}"
                        />
                        <input
                            type="hidden"
                            name="employee_name"
                            id="upload_employee_name_hidden"
                            value="{{ old('employee_name') }}"
                        />
                        <input
                            type="hidden"
                            name="document_id"
                            id="upload_document_id"
                            value="{{ old('document_id') }}"
                        />
                        <input
                            type="hidden"
                            name="document_name"
                            id="upload_document_name_hidden"
                            value="{{ old('document_name') }}"
                        />
                        <div class="form-group">
                            <label for="upload_document_name"
                                >Document Type</label
                            >
                            <input
                                type="text"
                                class="form-control bg-light"
                                id="upload_document_name"
                                value="{{ old('document_name') }}"
                                readonly
                            />
                        </div>
                        <div class="form-group">
                            <label for="upload_file"
                                >Select File
                                <span class="text-danger">*</span></label
                            >
                            <input
                                type="file"
                                name="file"
                                id="upload_file"
                                class="filepond {{ FormValidation::invalidClass('file', 'employee_doc_upload') }}"
                                data-accepted-file-types=".pdf,.jpg,.jpeg,.png"
                                data-max-file-size="5MB"
                                required
                            />
                            <x-ui.form-error field="file" context="employee_doc_upload" />
                            <small class="text-muted mt-2"
                                >Allowed: PDF, JPG, PNG (Max 5MB)</small
                            >
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="upload_issued_at">
                                    Issuing Date
                                    <span
                                        class="text-danger d-none"
                                        id="upload_issued_required"
                                        >*</span
                                    >
                                </label>
                                <input
                                    type="date"
                                    name="issued_at"
                                    id="upload_issued_at"
                                    class="form-control {{ FormValidation::invalidClass('issued_at', 'employee_doc_upload') }}"
                                    value="{{ old('issued_at') }}"
                                />
                                <x-ui.form-error field="issued_at" context="employee_doc_upload" />
                                <small
                                    class="text-muted mt-2"
                                    id="upload_issued_note"
                                >
                                    Optional. Leave blank if not applicable.
                                </small>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="upload_expires_at">
                                    Expiration Date
                                    <span
                                        class="text-danger d-none"
                                        id="upload_expires_required"
                                        >*</span
                                    >
                                </label>
                                <input
                                    type="date"
                                    name="expires_at"
                                    id="upload_expires_at"
                                    class="form-control {{ FormValidation::invalidClass('expires_at', 'employee_doc_upload') }}"
                                    value="{{ old('expires_at') }}"
                                />
                                <x-ui.form-error field="expires_at" context="employee_doc_upload" />
                                <small
                                    class="text-muted mt-2"
                                    id="upload_expires_note"
                                    >Optional. Leave blank if not
                                    applicable.</small
                                >
                            </div>
                        </div>
                    </div>
                </form>
                <x-ui.modal-footer>
                    <x-ui.button
                        variant="light"
                        size="sm"
                        data-coreui-dismiss="modal"
                    >
                        Cancel
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        variant="primary"
                        size="sm"
                        icon="cil-cloud-upload"
                        form="documentUploadForm"
                    >
                        Start Upload
                    </x-ui.button>
                </x-ui.modal-footer>
    </x-modal>
    {{-- ===================== 3. MASTER CATALOG SECTION ===================== --}}
    @if (auth()->user()->role === 'admin')
        @if ($isCatalogRoute && auth()->user()->isAdmin())
        <div class="card mb-4 border-0 hrms-list-card document-directory-card" id="documentCatalogSection">
            <div class="card-header bg-white py-3">
                <div class="d-flex flex-wrap align-items-center">
                    <h3
                        class="card-title font-weight-bold text-dark mb-2 mb-md-0"
                    >
                        <i class="cil-layers mr-2 text-secondary"></i>Document
                        Catalog Setup
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <div class="tab-content" id="documentCatalogTabsContent">
                    <div
                        class="tab-pane fade show active"
                        id="doc-categories"
                        role="tabpanel"
                        aria-labelledby="doc-categories-tab"
                    >
                        <x-ui.table-card
                            title="Document Categories"
                            subtitle="Maintain top-level catalog categories."
                        >
                            <table
                                id="categoriesTable"
                                class="table table-hover align-middle mb-0 datatable hrms-table"
                            >
                                <thead class="bg-light text-uppercase small">
                                    <tr>
                                        <th class="py-3">Category Name</th>
                                        <th class="py-3 text-center">
                                            Subcategories
                                        </th>
                                        <th class="py-3 text-center">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($categories as $category)
                                        <tr>
                                            <td
                                                class="align-middle font-weight-bold text-dark"
                                            >
                                                {{ $category->name }}
                                            </td>
                                            <td
                                                class="align-middle text-center"
                                            >
                                                {{ $category->subcategories_count ?? 0 }}
                                            </td>
                                            <td
                                                class="align-middle text-center"
                                            >
                                                <div
                                                    class="crud-actions justify-content-center"
                                                >
                                                    <x-ui.button
                                                        type="edit"
                                                        size="sm"
                                                        data-toggle="modal"
                                                        data-target="#categoryEditModal"
                                                        data-edit="{{ json_encode(['update_url' => route('documents.categories.update', $category), 'name' => $category->name]) }}"
                                                        data-update-url="{{ route('documents.categories.update', $category) }}"
                                                        data-name="{{ $category->name }}"
                                                        aria-label="Edit Category"
                                                        title="Edit Category"
                                                    />
                                                    @if (auth()->user()->isAdmin())
                                                        <form
                                                            action="{{ route('documents.categories.destroy', $category) }}"
                                                            method="POST"
                                                            class="d-inline"
                                                            data-confirm-message="Delete {{ $category->name }}?"
                                                            data-confirm-title="Delete Category"
                                                            data-confirm-label="Delete"
                                                            data-confirm-variant="danger"
                                                        >
                                                            @csrf
                                                            @method ('DELETE')
                                                            <x-ui.button
                                                                type="submit"
                                                                variant="delete"
                                                                size="sm"
                                                                aria-label="Delete Category"
                                                                title="Delete Category"
                                                            />
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td
                                                colspan="3"
                                                class="text-center py-5"
                                            >
                                                <i
                                                    class="cil-folder-open fs-2 mb-3 text-muted"
                                                ></i>
                                                <div class="hrms-empty-state">
                                                    <div class="hrms-empty-state__icon">
                                                        <i class="cil-folder-open"></i>
                                                    </div>
                                                    <div class="hrms-empty-state__title">No Categories Created</div>
                                                    <div class="hrms-empty-state__text">Create a document category to organize catalog requirements.</div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </x-ui.table-card>
                    </div>
                    <div
                        class="tab-pane fade"
                        id="doc-subcategories"
                        role="tabpanel"
                        aria-labelledby="doc-subcategories-tab"
                    >
                        <x-ui.table-card
                            title="Document Subcategories"
                            subtitle="Maintain grouped catalog sections."
                        >
                            <table
                                id="subcategoriesTable"
                                class="table table-hover align-middle mb-0 datatable hrms-table"
                            >
                                <thead class="bg-light text-uppercase small">
                                    <tr>
                                        <th class="py-3">Subcategory Name</th>
                                        <th class="py-3">Parent Category</th>
                                        <th class="py-3 text-center">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($subcategories as $subcategory)
                                        <tr>
                                            <td
                                                class="align-middle font-weight-bold text-dark"
                                            >
                                                {{ $subcategory->name }}
                                            </td>
                                            <td class="align-middle">
                                                {{ $subcategory->category?->name ?? 'Unassigned' }}
                                            </td>
                                            <td
                                                class="align-middle text-center"
                                            >
                                                <div
                                                    class="crud-actions justify-content-center"
                                                >
                                                    <x-ui.button
                                                        type="edit"
                                                        size="sm"
                                                        data-toggle="modal"
                                                        data-target="#subcategoryEditModal"
                                                        data-edit="{{ json_encode(['update_url' => route('documents.subcategories.update', $subcategory), 'name' => $subcategory->name, 'category_id' => $subcategory->document_category_id]) }}"
                                                        data-update-url="{{ route('documents.subcategories.update', $subcategory) }}"
                                                        data-name="{{ $subcategory->name }}"
                                                        data-category-id="{{ $subcategory->document_category_id }}"
                                                        aria-label="Edit Subcategory"
                                                        title="Edit Subcategory"
                                                    />
                                                    @if (auth()->user()->isAdmin())
                                                        <form
                                                            action="{{ route('documents.subcategories.destroy', $subcategory) }}"
                                                            method="POST"
                                                            class="d-inline"
                                                            data-confirm-message="Delete {{ $subcategory->name }}?"
                                                            data-confirm-title="Delete Subcategory"
                                                            data-confirm-label="Delete"
                                                            data-confirm-variant="danger"
                                                        >
                                                            @csrf
                                                            @method ('DELETE')
                                                            <x-ui.button
                                                                type="submit"
                                                                variant="delete"
                                                                size="sm"
                                                                aria-label="Delete Subcategory"
                                                                title="Delete Subcategory"
                                                            />
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td
                                                colspan="3"
                                                class="text-center py-5"
                                            >
                                                <i
                                                    class="cil-list fs-2 mb-3 text-muted"
                                                ></i>
                                                <div class="hrms-empty-state">
                                                    <div class="hrms-empty-state__icon">
                                                        <i class="cil-list"></i>
                                                    </div>
                                                    <div class="hrms-empty-state__title">No Subcategories Created</div>
                                                    <div class="hrms-empty-state__text">Create a document subcategory to group related requirements.</div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </x-ui.table-card>
                    </div>
                    <div
                        class="tab-pane fade"
                        id="doc-documents"
                        role="tabpanel"
                        aria-labelledby="doc-documents-tab"
                    >
                        <x-ui.table-card
                            title="Document Requirements"
                            subtitle="Maintain compliance requirements in the catalog."
                        >
                            <x-slot:controls>
                                <x-ui.table-toolbar
                                    as="div"
                                    id="documentsCatalogToolbar"
                                    class="documents-catalog-toolbar"
                                    searchName="documents_catalog_search"
                                    searchLabel="Search"
                                    searchPlaceholder="Search requirement, category, or subcategory"
                                    submitLabel="Apply"
                                />
                            </x-slot:controls>
                            <table
                                id="documentsTable"
                                class="table table-hover align-middle mb-0 datatable hrms-table"
                            >
                                <thead class="bg-light text-uppercase small">
                                    <tr>
                                        <th class="py-3">Requirement Name</th>
                                        <th class="py-3">Category</th>
                                        <th class="py-3">Subcategory</th>
                                        <th class="py-3 text-center">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($documents as $catalogDoc)
                                        <tr data-search="{{ $catalogDoc->document }}">
                                            <td
                                                class="py-3 align-middle font-weight-bold text-dark"
                                            >
                                                <i
                                                    class="cil-check text-muted mr-2"
                                                ></i>
                                                {{ $catalogDoc->document }}
                                                @if ($catalogDoc->gender)
                                                    <span
                                                        class="badge badge-info ml-2 font-weight-normal"
                                                        >{{ ucfirst($catalogDoc->gender) }} Only</span
                                                    >
                                                @endif
                                            </td>
                                            <td class="align-middle">
                                                {{ $catalogDoc->category?->name ?? '-' }}
                                            </td>
                                            <td class="align-middle">
                                                {{ $catalogDoc->subcategory?->name ?? '-' }}
                                            </td>
                                            <td
                                                class="align-middle text-center"
                                            >
                                                <div
                                                    class="crud-actions justify-content-center"
                                                >
                                                    <x-ui.button
                                                        type="edit"
                                                        size="sm"
                                                        data-toggle="modal"
                                                        data-target="#catalogEditModal"
                                                        data-edit="{{ json_encode(['update_url' => route('documents.update', $catalogDoc), 'name' => $catalogDoc->document, 'gender' => $catalogDoc->gender, 'category_id' => $catalogDoc->document_category_id, 'subcategory_id' => $catalogDoc->document_subcategory_id]) }}"
                                                        data-update-url="{{ route('documents.update', $catalogDoc) }}"
                                                        data-name="{{ $catalogDoc->document }}"
                                                        data-gender="{{ $catalogDoc->gender }}"
                                                        data-category-id="{{ $catalogDoc->document_category_id }}"
                                                        data-subcategory-id="{{ $catalogDoc->document_subcategory_id }}"
                                                        aria-label="Edit Document Type"
                                                        title="Edit Document Type"
                                                    />
                                                    @if (auth()->user()->isAdmin())
                                                        <form
                                                            action="{{ route('documents.destroy', $catalogDoc) }}"
                                                            method="POST"
                                                            class="d-inline"
                                                            data-confirm-message="Delete {{ $catalogDoc->document }}?"
                                                            data-confirm-title="Delete Document Requirement"
                                                            data-confirm-label="Delete"
                                                            data-confirm-variant="danger"
                                                        >
                                                            @csrf
                                                            @method ('DELETE')
                                                            <x-ui.button
                                                                type="submit"
                                                                variant="delete"
                                                                size="sm"
                                                                aria-label="Delete Document Requirement"
                                                                title="Delete Document Requirement"
                                                            />
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td
                                                colspan="4"
                                                class="text-center py-5"
                                            >
                                                <i
                                                    class="cil-description fs-2 mb-3 text-muted"
                                                ></i>
                                                <div class="hrms-empty-state">
                                                    <div class="hrms-empty-state__icon">
                                                        <i class="cil-description"></i>
                                                    </div>
                                                    <div class="hrms-empty-state__title">No Requirements Found</div>
                                                    <div class="hrms-empty-state__text">Add a document requirement to start building the compliance catalog.</div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                            <x-slot:footer>
                                {{ $documents->links() }}
                            </x-slot:footer>
                        </x-ui.table-card>
                    </div>
                </div>
            </div>
        </div>
        @endif {{-- end catalog/admin branch --}}
    @endif {{-- end admin-only catalog wrapper --}}
    </div>
@endsection
