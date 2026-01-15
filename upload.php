<?php
header('Content-Type: application/json');

$tmpDir = __DIR__ . '/tmp/';
$zipPath = $tmpDir . 'tran_sub.zip';

$logs = [];
$success = true;

if (!is_dir($tmpDir)) mkdir($tmpDir, 0777, true);

$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

$mode = $_POST['mode'] ?? 'smi2srt';

foreach ($_FILES['files']['tmp_name'] as $i => $tmpFile) {
  $name = $_FILES['files']['name'][$i];
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

  $logs[] = "변환 중: $name";

  try {
    $content = file_get_contents($tmpFile);

    if ($mode === 'smi2srt' && $ext === 'smi') {
      $out = smiToSrt($content);
      $outName = pathinfo($name, PATHINFO_FILENAME) . '.srt';
    } elseif ($mode === 'srt2smi' && $ext === 'srt') {
      $out = srtToSmi($content);
      $outName = pathinfo($name, PATHINFO_FILENAME) . '.smi';
    } else {
      throw new Exception('파일 형식 불일치');
    }

    $zip->addFromString($outName, $out);
  } catch (Exception $e) {
    $logs[] = "실패: {$e->getMessage()}";
    $success = false;
  }
}

$zip->close();

echo json_encode([
  'success' => $success,
  'logs' => $logs,
  'zip' => $success ? 'tmp/tran_sub.zip' : null
]);

// ---------------- 변환 함수 ----------------

function smiToSrt($smi) {
  $smi = preg_replace('/<[^>]+>/', '', $smi);
  $lines = preg_split("/\r\n|\n|\r/", trim($smi));

  $srt = '';
  $i = 1;
  $time = 0;

  foreach ($lines as $line) {
    if (!trim($line)) continue;
    $srt .= $i++ . "\n";
    $srt .= gmdate("H:i:s", $time) . ",000 --> " . gmdate("H:i:s", $time + 2) . ",000\n";
    $srt .= $line . "\n\n";
    $time += 3;
  }
  return $srt;
}

function srtToSmi($srt) {
  $lines = preg_split("/\r\n|\n|\r/", trim($srt));
  $smi = "<SAMI>\n<BODY>\n";

  foreach ($lines as $line) {
    if (preg_match('/^\d+$/', $line)) continue;
    if (strpos($line, '-->') !== false) continue;
    if (!trim($line)) continue;
    $smi .= "<SYNC Start=0><P>$line\n";
  }

  return $smi . "</BODY>\n</SAMI>";
}
