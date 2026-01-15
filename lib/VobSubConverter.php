<?php
// lib/VobSubConverter.php

class VobSubConverter {
    // 파일 목록에서 .idx와 .sub 쌍을 찾아 반환
    public static function findPairs($filePaths) {
        $pairs = [];
        $tempMap = [];

        foreach ($filePaths as $path) {
            $info = pathinfo($path);
            $ext = strtolower($info['extension']);
            $name = $info['filename'];

            if ($ext === 'idx' || $ext === 'sub') {
                $tempMap[$name][$ext] = $path;
            }
        }

        // 쌍이 완성된 경우만 필터링
        foreach ($tempMap as $name => $files) {
            if (isset($files['idx']) && isset($files['sub'])) {
                $pairs[] = [
                    'name' => $name,
                    'idx' => $files['idx'],
                    'sub' => $files['sub']
                ];
            }
        }

        return $pairs;
    }

    public function convert($idxPath, $outputDir) {
        // vobsub2srt가 설치되어 있는지 확인 (실제 운영환경에선 path 설정 필요)
        $cmd = "vobsub2srt -o " . escapeshellarg($outputDir) . " " . escapeshellarg(preg_replace('/\.idx$/', '', $idxPath));
        
        // 명령어 실행
        $output = [];
        $returnVar = 0;
        exec($cmd . " 2>&1", $output, $returnVar);

        if ($returnVar !== 0) {
            throw new Exception("VobSub 변환 실패 (vobsub2srt 필요): " . implode("\n", $output));
        }

        // 생성된 srt 파일 경로 찾기
        $baseName = pathinfo($idxPath, PATHINFO_FILENAME);
        $srtFile = $outputDir . '/' . $baseName . '.srt';

        if (!file_exists($srtFile)) {
             throw new Exception("변환 결과물을 찾을 수 없습니다.");
        }

        return file_get_contents($srtFile);
    }
    
    // 테스트용 헬퍼
    public function getCommand($idxPath) {
        return "vobsub2srt " . $idxPath;
    }
}