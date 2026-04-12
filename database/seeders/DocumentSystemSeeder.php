<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DocumentSystemSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // =====================================================================
        // 1. CATEGORIES
        // =====================================================================
        $categories = [
            ['id' => 1, 'name' => 'Personal and Credentials'],
            ['id' => 2, 'name' => 'Employment and Movement'],
            ['id' => 3, 'name' => 'Performance and Compliance'],
            ['id' => 4, 'name' => 'Separation and Off-boarding'],
            ['id' => 5, 'name' => 'Administrative and Legal'],
        ];

        foreach ($categories as $cat) {
            DB::table('document_categories')->updateOrInsert(
                ['id' => $cat['id']],
                array_merge($cat, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        // =====================================================================
        // 2. SUBCATEGORIES
        // =====================================================================
        $subCategories = [
            // --- Personal and Credentials ---
            ['id' =>  1, 'document_category_id' => 1, 'name' => 'Identity and Civil Status'],
            ['id' =>  2, 'document_category_id' => 1, 'name' => 'Education and Eligibility'],
            ['id' =>  3, 'document_category_id' => 1, 'name' => 'Legal and Health Clearances'],
            ['id' => 12, 'document_category_id' => 1, 'name' => 'Government Membership Records'],

            // --- Employment and Movement ---
            ['id' =>  4, 'document_category_id' => 2, 'name' => 'Appointment and Contracts'],
            ['id' => 13, 'document_category_id' => 2, 'name' => 'Personnel Data and Pre-Employment'],
            ['id' =>  5, 'document_category_id' => 2, 'name' => 'Compensation and Benefits'],

            // --- Performance and Compliance ---
            ['id' =>  6, 'document_category_id' => 3, 'name' => 'Evaluations and Training'],
            ['id' =>  7, 'document_category_id' => 3, 'name' => 'Compliance and Attendance'],

            // --- Separation and Off-boarding ---
            ['id' =>  8, 'document_category_id' => 4, 'name' => 'Exit Documents'],

            // --- Administrative and Legal ---
            ['id' =>  9, 'document_category_id' => 5, 'name' => 'Conduct and Discipline'],
            ['id' => 10, 'document_category_id' => 5, 'name' => 'Asset and Property Accountability'],
            ['id' => 11, 'document_category_id' => 5, 'name' => 'Health and Occupational Safety'],
            ['id' => 14, 'document_category_id' => 5, 'name' => 'Data Privacy and Agreements'],
        ];

        foreach ($subCategories as $sub) {
            DB::table('document_subcategories')->updateOrInsert(
                ['id' => $sub['id']],
                array_merge($sub, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        // =====================================================================
        // 3. DOCUMENTS
        // =====================================================================
        $documents = [

            // -----------------------------------------------------------------
            // Identity and Civil Status (cat:1, sub:1)
            // -----------------------------------------------------------------
            ['document_category_id' => 1, 'document_subcategory_id' =>  1, 'document' => 'PSA Birth Certificate',                            'document_type' => 'Permanent'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  1, 'document' => 'PSA Marriage Contract',                            'document_type' => 'Permanent',  'gender' => 'female'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  1, 'document' => 'Affidavit of Change of Name or Signature',         'document_type' => 'Permanent',  'gender' => 'female'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  1, 'document' => 'Passport Copy',                                    'document_type' => 'Renewable'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  1, 'document' => 'PhilSys National ID Copy',                         'document_type' => 'Permanent'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  1, 'document' => 'TIN Card Copy',                                    'document_type' => 'Permanent'],

            // -----------------------------------------------------------------
            // Education and Eligibility (cat:1, sub:2)
            // -----------------------------------------------------------------
            ['document_category_id' => 1, 'document_subcategory_id' =>  2, 'document' => 'Transcript of Records',                            'document_type' => 'Permanent'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  2, 'document' => 'Diploma',                                          'document_type' => 'Permanent'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  2, 'document' => 'PRC License',                                      'document_type' => 'Renewable'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  2, 'document' => 'CSC Eligibility Certificate',                      'document_type' => 'Permanent'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  2, 'document' => 'Certificate of Training or Seminar',               'document_type' => 'Permanent'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  2, 'document' => 'Professional Membership Certificate',              'document_type' => 'Renewable'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  2, 'document' => 'Awards and Recognition Certificate',               'document_type' => 'Permanent'],

            // -----------------------------------------------------------------
            // Legal and Health Clearances (cat:1, sub:3)
            // -----------------------------------------------------------------
            ['document_category_id' => 1, 'document_subcategory_id' =>  3, 'document' => 'NBI Clearance',                                    'document_type' => 'Renewable'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  3, 'document' => 'Police Clearance',                                 'document_type' => 'Renewable'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  3, 'document' => 'Barangay Clearance',                               'document_type' => 'Renewable'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  3, 'document' => 'Medical Certificate',                              'document_type' => 'Renewable'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  3, 'document' => 'Drug Test Certificate (Pre-Employment)',            'document_type' => 'Renewable'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  3, 'document' => 'Health Certificate (LGU-Issued)',                   'document_type' => 'Renewable'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  3, 'document' => 'Magna Carta for Women Medical Certificate',        'document_type' => 'Renewable',  'gender' => 'female'],
            ['document_category_id' => 1, 'document_subcategory_id' =>  3, 'document' => 'VAWC Leave Certification or Protection Order',     'document_type' => 'Permanent',  'gender' => 'female'],

            // -----------------------------------------------------------------
            // Government Membership Records (cat:1, sub:12)
            // -----------------------------------------------------------------
            ['document_category_id' => 1, 'document_subcategory_id' => 12, 'document' => 'SSS Member Data Record (E-1 or E-4)',              'document_type' => 'Permanent'],
            ['document_category_id' => 1, 'document_subcategory_id' => 12, 'document' => 'PhilHealth Membership Data Form (PMRF)',            'document_type' => 'Permanent'],
            ['document_category_id' => 1, 'document_subcategory_id' => 12, 'document' => 'Pag-IBIG Membership Registration Form (MDF)',      'document_type' => 'Permanent'],
            ['document_category_id' => 1, 'document_subcategory_id' => 12, 'document' => 'GSIS Member Record',                               'document_type' => 'Permanent'],
            ['document_category_id' => 1, 'document_subcategory_id' => 12, 'document' => 'BIR Form 1902 (TIN Enrollment)',                   'document_type' => 'Permanent'],

            // -----------------------------------------------------------------
            // Appointment and Contracts (cat:2, sub:4)
            // -----------------------------------------------------------------
            ['document_category_id' => 2, 'document_subcategory_id' =>  4, 'document' => 'Employment Contract',                              'document_type' => 'Renewable'],
            ['document_category_id' => 2, 'document_subcategory_id' =>  4, 'document' => 'Job Offer or Acceptance Letter',                   'document_type' => 'Permanent'],
            ['document_category_id' => 2, 'document_subcategory_id' =>  4, 'document' => 'Oath of Office',                                   'document_type' => 'Permanent'],
            ['document_category_id' => 2, 'document_subcategory_id' =>  4, 'document' => 'Job Description (PDF)',                            'document_type' => 'Permanent'],
            ['document_category_id' => 2, 'document_subcategory_id' =>  4, 'document' => 'Designation or Promotion Order',                   'document_type' => 'Permanent'],
            ['document_category_id' => 2, 'document_subcategory_id' =>  4, 'document' => 'Probationary Evaluation Form',                     'document_type' => 'Periodic'],

            // -----------------------------------------------------------------
            // Personnel Data and Pre-Employment (cat:2, sub:13)
            // -----------------------------------------------------------------
            ['document_category_id' => 2, 'document_subcategory_id' => 13, 'document' => 'Personnel Data Sheet - PDS (CS Form 212)',         'document_type' => 'Permanent'],
            ['document_category_id' => 2, 'document_subcategory_id' => 13, 'document' => 'Pre-Employment Medical Form',                      'document_type' => 'Permanent'],
            ['document_category_id' => 2, 'document_subcategory_id' => 13, 'document' => 'Application Letter',                               'document_type' => 'Permanent'],
            ['document_category_id' => 2, 'document_subcategory_id' => 13, 'document' => 'Resume',                                           'document_type' => 'Permanent'],
            ['document_category_id' => 2, 'document_subcategory_id' => 13, 'document' => '2x2 Photo ID',                                     'document_type' => 'Periodic'],
            ['document_category_id' => 2, 'document_subcategory_id' => 13, 'document' => 'Emergency Contact Form',                           'document_type' => 'Permanent'],
            ['document_category_id' => 2, 'document_subcategory_id' => 13, 'document' => 'Non-Disclosure Agreement (NDA)',                   'document_type' => 'Permanent'],

            // -----------------------------------------------------------------
            // Compensation and Benefits (cat:2, sub:5)
            // -----------------------------------------------------------------
            ['document_category_id' => 2, 'document_subcategory_id' =>  5, 'document' => 'Notice of Salary Adjustment (NOSA)',               'document_type' => 'Periodic'],
            ['document_category_id' => 2, 'document_subcategory_id' =>  5, 'document' => 'BIR Form 2316',                                    'document_type' => 'Annual'],
            ['document_category_id' => 2, 'document_subcategory_id' =>  5, 'document' => 'Leave of Absence Application',                     'document_type' => 'Periodic'],
            ['document_category_id' => 2, 'document_subcategory_id' =>  5, 'document' => 'Approved Leave Form',                              'document_type' => 'Periodic'],
            ['document_category_id' => 2, 'document_subcategory_id' =>  5, 'document' => 'Maternity Notification Form',                      'document_type' => 'Periodic',   'gender' => 'female'],
            ['document_category_id' => 2, 'document_subcategory_id' =>  5, 'document' => 'Maternity Leave Application',                      'document_type' => 'Periodic',   'gender' => 'female'],
            ['document_category_id' => 2, 'document_subcategory_id' =>  5, 'document' => 'Special Leave Benefit Application (SLB)',          'document_type' => 'Periodic',   'gender' => 'female'],
            ['document_category_id' => 2, 'document_subcategory_id' =>  5, 'document' => 'Paternity Leave Application',                      'document_type' => 'Periodic',   'gender' => 'male'],
            ['document_category_id' => 2, 'document_subcategory_id' =>  5, 'document' => 'Proof of Childbirth or Birth Certificate',         'document_type' => 'Permanent',  'gender' => 'male'],
            ['document_category_id' => 2, 'document_subcategory_id' =>  5, 'document' => 'Solo Parent Leave Application',                    'document_type' => 'Periodic'],

            // -----------------------------------------------------------------
            // Evaluations and Training (cat:3, sub:6)
            // -----------------------------------------------------------------
            ['document_category_id' => 3, 'document_subcategory_id' =>  6, 'document' => 'Performance Evaluation (IPCR)',                    'document_type' => 'Periodic'],
            ['document_category_id' => 3, 'document_subcategory_id' =>  6, 'document' => 'Individual Development Plan (IDP)',                'document_type' => 'Periodic'],
            ['document_category_id' => 3, 'document_subcategory_id' =>  6, 'document' => 'Coaching and Mentoring Record',                    'document_type' => 'Periodic'],

            // -----------------------------------------------------------------
            // Compliance and Attendance (cat:3, sub:7)
            // -----------------------------------------------------------------
            ['document_category_id' => 3, 'document_subcategory_id' =>  7, 'document' => 'SALN (Statement of Assets, Liabilities and Net Worth)', 'document_type' => 'Annual'],
            ['document_category_id' => 3, 'document_subcategory_id' =>  7, 'document' => 'Daily Time Record (DTR)',                          'document_type' => 'Monthly'],

            // -----------------------------------------------------------------
            // Exit Documents (cat:4, sub:8)
            // -----------------------------------------------------------------
            ['document_category_id' => 4, 'document_subcategory_id' =>  8, 'document' => 'Resignation Letter',                               'document_type' => 'Permanent'],
            ['document_category_id' => 4, 'document_subcategory_id' =>  8, 'document' => 'Exit Interview Form',                              'document_type' => 'Permanent'],
            ['document_category_id' => 4, 'document_subcategory_id' =>  8, 'document' => 'Final Clearance Checklist',                        'document_type' => 'Permanent'],
            ['document_category_id' => 4, 'document_subcategory_id' =>  8, 'document' => 'Certificate of No Pending Accountability',         'document_type' => 'Permanent'],
            ['document_category_id' => 4, 'document_subcategory_id' =>  8, 'document' => 'Quitclaim or Release and Waiver',                  'document_type' => 'Permanent'],
            ['document_category_id' => 4, 'document_subcategory_id' =>  8, 'document' => 'Clearance from Money or Property Accountability',  'document_type' => 'Permanent'],
            ['document_category_id' => 4, 'document_subcategory_id' =>  8, 'document' => 'Certificate of Employment (Issued)',               'document_type' => 'Permanent'],

            // -----------------------------------------------------------------
            // Conduct and Discipline (cat:5, sub:9)
            // -----------------------------------------------------------------
            ['document_category_id' => 5, 'document_subcategory_id' =>  9, 'document' => 'Formal Complaint Form',                            'document_type' => 'Permanent'],
            ['document_category_id' => 5, 'document_subcategory_id' =>  9, 'document' => 'Notice to Explain (NTE)',                          'document_type' => 'Permanent'],
            ['document_category_id' => 5, 'document_subcategory_id' =>  9, 'document' => 'Notice of Disciplinary Action',                    'document_type' => 'Permanent'],
            ['document_category_id' => 5, 'document_subcategory_id' =>  9, 'document' => 'Written Reprimand',                                'document_type' => 'Permanent'],
            ['document_category_id' => 5, 'document_subcategory_id' =>  9, 'document' => 'Administrative Decision or Resolution',            'document_type' => 'Permanent'],

            // -----------------------------------------------------------------
            // Asset and Property Accountability (cat:5, sub:10)
            // -----------------------------------------------------------------
            ['document_category_id' => 5, 'document_subcategory_id' => 10, 'document' => 'Equipment Custodianship Form',                     'document_type' => 'Renewable'],
            ['document_category_id' => 5, 'document_subcategory_id' => 10, 'document' => 'IT Asset Acknowledgement',                         'document_type' => 'Permanent'],
            ['document_category_id' => 5, 'document_subcategory_id' => 10, 'document' => 'Vehicle or Transport Use Agreement',               'document_type' => 'Renewable'],
            ['document_category_id' => 5, 'document_subcategory_id' => 10, 'document' => 'Property Return Slip',                             'document_type' => 'Permanent'],

            // -----------------------------------------------------------------
            // Health and Occupational Safety (cat:5, sub:11)
            // -----------------------------------------------------------------
            ['document_category_id' => 5, 'document_subcategory_id' => 11, 'document' => 'Annual Physical Examination Result',               'document_type' => 'Annual'],
            ['document_category_id' => 5, 'document_subcategory_id' => 11, 'document' => 'Random Drug Test Result',                          'document_type' => 'Renewable'],
            ['document_category_id' => 5, 'document_subcategory_id' => 11, 'document' => 'OB-GYN Clearance or Medical Certificate',          'document_type' => 'Renewable',  'gender' => 'female'],
            ['document_category_id' => 5, 'document_subcategory_id' => 11, 'document' => 'Mammogram or Cervical Cancer Screening Results',   'document_type' => 'Annual',     'gender' => 'female'],
            ['document_category_id' => 5, 'document_subcategory_id' => 11, 'document' => 'Prostate Exam or PSA Results',                    'document_type' => 'Renewable',  'gender' => 'male'],

            // -----------------------------------------------------------------
            // Data Privacy and Agreements (cat:5, sub:14)
            // -----------------------------------------------------------------
            ['document_category_id' => 5, 'document_subcategory_id' => 14, 'document' => 'Data Privacy Consent Form',                        'document_type' => 'Permanent'],
            ['document_category_id' => 5, 'document_subcategory_id' => 14, 'document' => 'ICT Usage Policy Agreement',                       'document_type' => 'Permanent'],
            ['document_category_id' => 5, 'document_subcategory_id' => 14, 'document' => 'Conflict of Interest Disclosure Form',             'document_type' => 'Periodic'],
        ];

        foreach ($documents as $doc) {
            unset($doc['document_type']);
            DB::table('documents')->updateOrInsert(
                [
                    'document'              => $doc['document'],
                    'document_category_id'  => $doc['document_category_id'],
                    'document_subcategory_id' => $doc['document_subcategory_id'],
                ],
                array_merge($doc, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}
