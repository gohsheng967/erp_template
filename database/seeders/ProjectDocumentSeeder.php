<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProjectDocument;

class ProjectDocumentSeeder extends Seeder
{
    public function run(): void
    {
        ProjectDocument::create([
            'project_id' => 1,
            'name' => 'Main Contract',
            'type' => 'contract',
            'version' => 1,
            'file_path' => 'documents/contracts/contract_v1.pdf'
        ]);

        ProjectDocument::create([
            'project_id' => 1,
            'name' => 'Project Proposal',
            'type' => 'proposal',
            'version' => 1,
            'file_path' => 'documents/proposals/erp_proposal.pdf'
        ]);
    }
}
