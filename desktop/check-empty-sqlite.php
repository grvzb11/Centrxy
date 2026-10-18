<?php

/** Refuse imports that could overwrite generated user data. Not part of runtime. */
if ($argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "Target SQLite file missing.\n");
    exit(2);
}
try {
    $db = new PDO('sqlite:'.$argv[1], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ($db->query('PRAGMA quick_check')->fetchColumn() !== 'ok') {
        throw new RuntimeException('Target database fails integrity check.');
    }
    foreach (['templates', 'template_elements', 'batches', 'certificates'] as $table) {
        $q = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
        $q->execute([$table]);
        if ($q->fetchColumn() !== false && (int) $db->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn() !== 0) {
            throw new RuntimeException("Destination $table already has records. Import blocked.");
        }
    }
    echo "Empty user-data tables verified.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(2);
}
