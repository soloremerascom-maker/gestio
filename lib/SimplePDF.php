<?php
class SimplePDF
{
    private array $lines = [];
    private string $title = '';

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function addLine(string $text): void
    {
        $this->lines[] = $text;
    }

    private function escape(string $text): string
    {
        $replacements = [
            '\\' => '\\\\',
            '(' => '\\(',
            ')' => '\\)',
            "\r" => '',
            "\n" => '\\n',
        ];
        return strtr($text, $replacements);
    }

    public function output(string $filename = 'documento.pdf'): void
    {
        $content = "BT\n/F1 14 Tf\n18 TL\n72 770 Td\n";
        if ($this->title !== '') {
            $content .= sprintf('(%s) Tj\nT*\n', $this->escape($this->title));
        }
        if (!empty($this->lines)) {
            $content .= "/F1 11 Tf\n";
            foreach ($this->lines as $line) {
                $content .= sprintf('(%s) Tj\nT*\n', $this->escape($line));
            }
        }
        $content .= "ET";

        $length = strlen($content);

        $objects = [];
        $objects[] = "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj";
        $objects[] = "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj";
        $objects[] = "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources<< /Font<< /F1 5 0 R >> >> >>endobj";
        $objects[] = "4 0 obj<< /Length $length >>stream\n$content\nendstream\nendobj";
        $objects[] = "5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . "\n";
        }
        $xrefPosition = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 $count\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer<< /Size $count /Root 1 0 R >>\nstartxref\n$xrefPosition\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }
}
