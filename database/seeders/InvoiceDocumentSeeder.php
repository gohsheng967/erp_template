<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceDocumentSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Document Type
        $typeId = DB::table('document_types')->insertGetId([
            'name'        => 'Invoice',
            'code'        => 'INVOICE',
            'description' => 'Standard supplier invoice',
            'version'     => 1,
            'is_active'   => true,
        ]);

        // 2. Fields
        DB::table('document_fields')->insert([
            [
                'document_type_id' => $typeId,
                'label' => 'Invoice No',
                'field_name' => 'invoice_no',
                'field_type' => 'text',
                'is_required' => true,
                'order' => 1,
            ],
            [
                'document_type_id' => $typeId,
                'label' => 'Supplier Name',
                'field_name' => 'supplier_name',
                'field_type' => 'text',
                'is_required' => true,
                'order' => 2,
            ],
            [
                'document_type_id' => $typeId,
                'label' => 'Invoice Date',
                'field_name' => 'invoice_date',
                'field_type' => 'date',
                'is_required' => true,
                'order' => 3,
            ],
            [
                'document_type_id' => $typeId,
                'label' => 'Amount',
                'field_name' => 'amount',
                'field_type' => 'number',
                'is_required' => true,
                'order' => 4,
            ],
        ]);

        // 3. Template
        DB::table('document_templates')->insert([
            'document_type_id' => $typeId,
            'name' => 'Invoice Default Template',
            'version' => 1,
            'is_active' => true,
            'html_template' => '
                <html>
                <head>
                    <style>
                        body { font-family: DejaVu Sans; font-size:12px; }
                        @page { size: A4; margin: 20mm; }
                        .title { font-size:22px; font-weight:bold; margin-bottom:25px; }
                        table { width:100%; border-collapse:collapse; }
                        td { padding:8px; border:1px solid #000; }
                    </style>
                </head>
                <body>

                    <div class="title">INVOICE</div>

                    <table>
                        <tr><td>Invoice No</td><td>{{ invoice_no }}</td></tr>
                        <tr><td>Supplier</td><td>{{ supplier_name }}</td></tr>
                        <tr><td>Invoice Date</td><td>{{ invoice_date }}</td></tr>
                        <tr><td>Amount</td><td>{{ amount }}</td></tr>
                    </table>

                </body>
                </html>
            ',
            'css' => null,
        ]);
    }
}
