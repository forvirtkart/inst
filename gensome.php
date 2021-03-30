<?php

function zipData($source, $destination) {
    if (extension_loaded('zip')) {
        if (file_exists($source)) {
            $zip = new ZipArchive();
            if ($zip->open($destination, ZIPARCHIVE::CREATE)) {
                $source = realpath($source);
                if (is_dir($source)) {
                    $iterator = new RecursiveDirectoryIterator($source);
                    // skip dot files while iterating
                    $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);
                    $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
                    foreach ($files as $file) {
                        $file = realpath($file);
                        if (is_dir($file)) {
                            $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                        } else if (is_file($file)) {
                            $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                        }
                    }
                } else if (is_file($source)) {
                    $zip->addFromString(basename($source), file_get_contents($source));
                }
            }
            return $zip->close();
        }
    }
    return false;
}

function file_force_download($file) {
  if (file_exists($file)) {
    // сбрасываем буфер вывода PHP    
    if (ob_get_level()) {
      ob_end_clean();
    }
    // заставляем браузер показать окно сохранения файла
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . basename($file));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    // читаем файл и отправляем его пользователю
    readfile($file);
    //exit;
  }
}

$date=date('Y-m-d_H-i-s');
$dir=__DIR__.'/'.$date;
mkdir($dir);

$nicks=explode(PHP_EOL,$_POST['nicks']); // get instanicks
$zip= 1; //$_POST["zip"]; // should we download archive
libxml_use_internal_errors(true);

foreach ($nicks as &$nick) {
    $nick=trim($nick);
    $curdir=$dir.'/'.$nick;
    mkdir($curdir);
    //die(var_dump($_POST['nicks']));
    $doc = new DomDocument();
    //$doc->loadHTML(file_get_contents("https://www.instagram.com/$nick/"));
	//proxy
	$auth = base64_encode('kZaQax:0NohdY');
    $aContext = array(
    'http' => array(
        'proxy'           => 'tcp://85.115.202.85:8000',
        'request_fulluri' => true,
        'header'          => "Proxy-Authorization: Basic $auth",
        ),
     );
     $cxContext = stream_context_create($aContext);

    $doc->loadHTML(file_get_contents("https://www.instagram.com/$nick/", False, $cxContext));
	///////////////
	
    $xpath = new DOMXPath($doc);
    $query = '//*/meta[starts-with(@property, \'og:\')]';

    $metas = $xpath->query($query);
     //print_r($doc);
    foreach ($metas as $meta) {
        $property = $meta->getAttribute('property');
        $content = $meta->getAttribute('content');
        $data[$property] = $content;
        // echo '<h1>Meta '.$property.' <span>'.$content.'</span></h1>' ;

    }
    $xpath1 = new DOMXPath($doc);
    $scripts = $xpath1->query('//head//script[starts-with(@type, \'application/ld+json\')]');

    foreach ($scripts as $script) {

        $json = json_decode($script->textContent, false);
        //$desc=end(explode("\n",$json->description));
        $desc = mb_ereg_replace('\n', '<br/>', $json->description);
        //$desc=str_replace('\n','<br/>',$json->description);

    }

    if ((!isset($data['og:title'])) or (!isset($data['og:image'])) or
        (!isset($data['og:description'])) or (!isset($data['og:url']))) {
        return 42; // Что-то пошло не так
    }

    $img = file_get_contents($data['og:image']);
    $image_name = explode('?', end(explode('/', $data['og:image'])));
// echo $image_name[0];
    file_put_contents($curdir . '/logo.jpg', $img);


    $template = file_get_contents(__DIR__ . '/insttemplate/index.html');
    $replaces = ['%logo_url%' => 'og:image', '%ogdesc%' => 'og:description', '%desc%' => 'description', '%url%' => 'og:url', '%title%' => 'og:title'];
    foreach ($replaces as $key => $replace) {
        $r = $data[$replace];
        //echo $r;
        if ($replace == 'og:title') {
            //echo $r;
            $r = mb_strcut($r, 0, mb_strpos($r, '(@'));
            // echo $r;
        }
        if ($replace == 'og:image') {
            $r = 'logo.jpg';
        }
        if ($replace == 'description') {
            if (isset($desc)) $r = $desc;
        }
        $template = str_replace($key, $r, $template);
    }
    file_put_contents($curdir . '/index.html', $template);
    unset($doc,$xpath,$xpath1,$metas,$img,$image_name,$template);
}
if ($zip)
{
    zipData($dir,$dir."/$date.zip");
    //header("Location: $date/$date.zip");
	file_force_download($date.'/'.$date.'.zip');
	
	//удаление каталога
	foreach( new RecursiveIteratorIterator( 
    new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS ), 
    RecursiveIteratorIterator::CHILD_FIRST ) as $value ) {
        $value->isFile() ? unlink( $value ) : rmdir( $value );
    }
    rmdir($dir);
	
}

?>