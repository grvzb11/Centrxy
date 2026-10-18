<?php
return ['max_import_rows' => (int) env('CERT_MAX_IMPORT_ROWS', 1000), 'max_template_mb' => 20, 'immediate_limit' => 10, 'ghostscript_binary' => env('CERTIFY_GHOSTSCRIPT_BINARY', ''), 'qpdf_binary' => env('CERTIFY_QPDF_BINARY', '')];
