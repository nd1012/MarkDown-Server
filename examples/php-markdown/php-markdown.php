<?php

require_once 'Michelf/Markdown.inc.php';

use Michelf\Markdown;

function convertMarkDown($mdFile,$htmlFile){
	$html=Markdown::defaultTransform(file_get_contents($mdFile));
	if(!is_null($htmlFile)) file_put_contents($htmlFile,$html);
	return $html;
}
