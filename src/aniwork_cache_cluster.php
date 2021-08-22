<?php
/**
 * @package     Aniwork Cache Cluster
 * @author      HanbitGaram(https://hanb.jp)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 * 
 * 마스토돈(MASTODON)의 캐시 로드밸런싱 및 클러스터링을 위하여 간단히 만든 파일으로,
 * 사전에 .httpcache 폴더를 스토리지 서버와 마운트를 진행하여 사용해야합니다.
 * 
 * 굳이 마스토돈(MASTODON)이 아니더라도, 다양한 환경에서 응용하여 사용할 수 있습니다.
 * 
 * 임의로 만든 파일이므로 코드 정리를 하지 않았으며, PHP8.0 환경에서 구동될 수 있도록
 * 코드가 구현되어 있으므로, 개발자들이 사용하기에는 안전할지 몰라도 맹신해선 아니됩니다.
 * 
 * 되도록 환경은 파일의 직접적인 접근을 차단하기 위하여 Apache를 추천드립니다.
 * 
 * Aniwork는 Storage 서버가 해외에 위치해 있는 관계로 지리적으로 오래걸리는 측면이 있어
 * 이런 방식으로 캐싱을 진행합니다.
 */

define('_CACHE_PREFIX_', 'filename_');
define('_STORAGE_DOMAIN_', 'S3 저장소 도메인');

// 전처리 (확장자 처리)
$extension = [
    'png'=>'image/png',
    'jpg'=>'image/jpeg',
    'jpeg'=>'image/jpeg',
    'gif'=>'image/gif',
    'mp4'=>'video/mp4',
];

$header = "Content-Type: ";

// 파일이 올바르지 않을 경우 그대로 404로 반환시킨다.
if(isset($_GET['path']))
    if(!trim($_GET['path'])) $_GET['path'] = null;


// 쿼리스트링 분리 (클라우드 플레어를 사용하거나 특수환경에서는 그냥 넘어가도 됨)
$_GET['path'] = strtok($_GET['path'], '?');
$_GET['path'] = preg_replace("[^a-zA-Z\_0-9\.\/]", '', $_GET['path']);

if(!isset($_GET['path'])){ 
    header("HTTP/1.0 404 Not Found");
    exit;
}

// 확장자 분리
// 굳이 이 아래에다가 넣는 이유는, isset 설정하기 귀찮아서 (...)
$ext = strtolower(pathinfo($_GET['path'], PATHINFO_EXTENSION));
$is_corrent = false;

// 파일이 확장자와 일치하는지 검증, 맞으면 헤더 삽입
foreach($extension as $key=>$value){
    if($ext===$key){
        $is_corrent = true;
        $header .= $value;
        break;
    }
}

// 파일이 확장자와 일치하지 않는다면 일단 거르고 봄
if($is_corrent===false){
    header("HTTP/1.0 404 Not Found");
    exit;
}

// 저장소에 저장할 파일명
$hashedUrl = ".".sha1(_CACHE_PREFIX_.$_GET['path']).'.'.$ext;
// echo $hashedUrl;
if(is_file('./.httpcache/'.$hashedUrl)){
    header($header);
    header("Cache-Control: max-age=14400, public");
    $fp = fopen('./.httpcache/'.$hashedUrl, 'rb');
    fpassthru($fp);
    fclose($fp);
    exit;
}

// 캐싱 파일 불러오기
$file = file_get_contents(_STORAGE_DOMAIN_.$_GET['path'], false, stream_context_create(['http' => ['ignore_errors' => true]]));

// RESPONSE가 정상이 아닌 경우, 404 리턴
if(!str_contains($http_response_header[0], "200")){
    header("HTTP/1.0 404 Not Found");
    exit;
}

// 파일 저장 및 불러오기
header($header);

// 여기에 GD를 사용하여 이미지를 압축하는 로직을 사용할 수 있습니다.
// 이미지를 압축하는 경우 케싱 스토리지의 용량을 절약할 수 있습니다.

// 파일이 정상인 경우
$fp = fopen('./.httpcache/'.$hashedUrl, 'wb');
fwrite($fp, $file);
fclose($fp);   

echo $file;