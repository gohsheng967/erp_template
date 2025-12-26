<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\DocumentField;
use App\Models\DocumentTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Browsershot\Browsershot;

class DocumentController extends Controller
{
    /* ================================================================
     | DOCUMENT TYPES LIST PAGE
     ================================================================= */
    public function types()
    {
        return Inertia::render('Documents/Types/Index', [
            'types' => DocumentType::withCount('fields')->get(),
        ]);
    }

    /* ================================================================
     | CREATE DOCUMENT TYPE
     ================================================================= */
    public function storeType(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:document_types,code',
        ]);

        DocumentType::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
        ]);

        return back()->with('success', 'Document type added.');
    }

    /* ================================================================
     | UPDATE DOCUMENT TYPE
     ================================================================= */
    public function updateType(Request $request, DocumentType $documentType)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $documentType->update([
            'name' => $request->name,
        ]);

        return back()->with('success', 'Document type updated.');
    }

    /* ================================================================
     | DELETE DOCUMENT TYPE (CASCADE DELETE)
     ================================================================= */
    public function destroyType(DocumentType $documentType)
    {
        $documentType->fields()->delete();
        $documentType->template()->delete();
        $documentType->delete();

        return back()->with('success', 'Document type deleted.');
    }

    /* ================================================================
     | FIELD BUILDER PAGE
     ================================================================= */
    public function editFields($code)
    {
        $document = DocumentType::where('code', $code)
            ->with('fields')
            ->firstOrFail();

        return Inertia::render('Documents/Fields/Builder', [
            'document' => $document,
        ]);
    }

    /* ================================================================
     | SAVE FIELDS (CREATE / UPDATE / DELETE)
     ================================================================= */
    public function saveFields(Request $request, $code)
    {
        $document = DocumentType::where('code', $code)->firstOrFail();

        $incoming = $request->fields ?? [];
        $savedIds = [];

        foreach ($incoming as $i => $field) {
            $record = DocumentField::updateOrCreate(
                ['id' => $field['id'] ?? null],
                [
                    'document_type_id' => $document->id,
                    'label'            => $field['label'],
                    'field_name'       => $field['field_name'],
                    'field_type'       => $field['field_type'],
                    'is_required'      => $field['is_required'] ?? false,
                    'order'            => $i + 1,
                ]
            );

            $savedIds[] = $record->id;
        }

        DocumentField::where('document_type_id', $document->id)
            ->whereNotIn('id', $savedIds)
            ->delete();

        return back()->with('success', 'Fields updated.');
    }

    /* ================================================================
     | TEMPLATE DESIGNER PAGE (JSON-based)
     ================================================================= */
    public function editTemplate($code)
    {
        $documentType = DocumentType::where('code', $code)->firstOrFail();

        $template = DocumentTemplate::firstOrCreate(
            ['document_type_id' => $documentType->id],
            [
                'json' => json_encode([
                    'header' => ['rows' => []],
                    'body'   => [],
                    'footer' => ['rows' => []],
                ]),
                'html_template' => '<h1>New Template</h1>',
                'css'           => '',
                'version'       => 1,
            ]
        );

        return Inertia::render('Documents/Template/Designer', [
            'template' => $template,
            'type'     => $documentType,
        ]);
    }

    /* ================================================================
     | SAVE TEMPLATE (JSON + HTML + VERSION)
     ================================================================= */
    public function updateTemplate(Request $request, $code)
    {
        $documentType = DocumentType::where('code', $code)->firstOrFail();

        $request->validate([
            'json'          => 'required',
            'html_template' => 'required|string',
        ]);

        $template = DocumentTemplate::firstOrCreate(
            ['document_type_id' => $documentType->id]
        );

        $template->update([
            'json'          => $request->json,
            'html_template' => $request->html_template,
            'css'           => $request->css ?? '',
            'version'       => $template->version + 1,
        ]);

        return back()->with('success', 'Template updated.');
    }

    /* ================================================================
     | PREVIEW TEMPLATE (PDF)
     ================================================================= */
    public function previewTemplate(Request $request, $code)
    {
        $request->validate([
            'html_template' => 'required|string',
            'css'           => 'nullable|string',
            'sample_data'   => 'nullable|array',
        ]);

        $html = $this->buildHtmlDocument(
            $request->html_template,
            $request->css ?? '',
            $request->sample_data ?? []
        );

        $pdf = Browsershot::html($html)
            ->format('A4')
            ->margins(20, 20, 20, 20)
            ->showBackground()
            ->pdf();

        return response($pdf)
            ->header('Content-Type', 'application/pdf');
    }

    /* ================================================================
     | PRIVATE HTML BUILDER
     ================================================================= */
    private function buildHtmlDocument($rawHtml, $css = '', $data = [])
    {
        foreach ($data as $key => $value) {
            $rawHtml = str_replace('{{ ' . $key . ' }}', e($value), $rawHtml);
        }

        $rawHtml = preg_replace('/\{\{[^}]+\}\}/', '', $rawHtml);

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>{$css}</style>
        </head>
        <body>
            {$rawHtml}
        </body>
        </html>";
    }
}
