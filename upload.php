<?php
// upload.php
require_once __DIR__ . '/lib/TextConverter.php';
require_once __DIR__ . '/lib/VobSubConverter.php';

header('Content-Type: application/json');

// 세션별 고유 작업 디렉토리 생성 (동시 접속 충돌 방지)
$jobId = uniqid();
$tmpDir = __DIR__ . '/tmp/' . $jobId . '/';
$zipPath = $tmpDir . 'tran_sub.zip';

if (!is_dir($tmpDir)) mkdir($tmpDir, 0777, true);

$logs = [];
$success = true;

try {
    // 1. 업로드된 모든 파일을 작업 디렉토리로 이동
    $uploadedFiles = [];
    if (!empty($_FILES['files']['name'][0])) {
        foreach ($_FILES['files']['tmp_name'] as $i => $tmpName) {
            $name = $_FILES['files']['name'][$i];
            $targetPath = $tmpDir . $name;
            if (move_uploaded_file($tmpName, $targetPath)) {
                $uploadedFiles[] = $targetPath;
            }
        }
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception("ZIP 파일을 생성할 수 없습니다.");
    }

    $mode = $_POST['mode'] ?? 'smi2srt';

    // 2. IDX+SUB 변환 처리 (파일 쌍이 필요한 경우)
    if ($mode === 'idx2srt') {
        $pairs = VobSubConverter::findPairs($uploadedFiles);
        $vobConverter = new VobSubConverter();

        foreach ($pairs as $pair) {
            $baseName = $pair['name'];
            $logs[] = "VobSub 변환 중: {$baseName} (.idx + .sub)";
            
            try {
                // 변환 실행 (결과물은 $tmpDir에 생성됨)
                $srtContent = $vobConverter->convert($pair['idx'], $tmpDir);
                $zip->addFromString($baseName . '.srt', $srtContent);
                
                // 처리된 파일 목록에서 제거 (중복 처리 방지용 - 선택 사항)
            } catch (Exception $e) {
                $logs[] = "실패 ($baseName): {$e->getMessage()}";
                $success = false; // 부분 실패도 실패로 간주할지 여부는 정책 결정
            }
        }
    }

    // 3. 단일 텍스트 파일 변환 처리
    foreach ($uploadedFiles as $filePath) {
        $info = pathinfo($filePath);
        $ext = strtolower($info['extension']);
        $name = $info['basename']; // 확장자 포함
        $filename = $info['filename']; // 확장자 제외

        // 이미 처리된 IDX/SUB 파일은 건너뛰기
        if (($mode === 'idx2srt') && ($ext === 'idx' || $ext === 'sub')) continue;

        try {
            $content = file_get_contents($filePath);
            $convertedContent = null;
            $outName = null;

            if ($mode === 'smi2srt' && $ext === 'smi') {
                $logs[] = "변환 중: $name (SMI -> SRT)";
                $convertedContent = TextConverter::smiToSrt($content);
                $outName = $filename . '.srt';
            } elseif ($mode === 'srt2smi' && $ext === 'srt') {
                $logs[] = "변환 중: $name (SRT -> SMI)";
                $convertedContent = TextConverter::srtToSmi($content);
                $outName = $filename . '.smi';
            }

            if ($convertedContent !== null) {
                $zip->addFromString($outName, $convertedContent);
            }
        } catch (Exception $e) {
            $logs[] = "실패 ($name): {$e->getMessage()}";
        }
    }

    $zip->close();

} catch (Exception $e) {
    $logs[] = "시스템 오류: " . $e->getMessage();
    $success = false;
}

echo json_encode([
    'success' => $success,
    'logs' => $logs,
    'zip' => $success ? "tmp/$jobId/tran_sub.zip" : null
]);
?>