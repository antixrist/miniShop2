<?php
/* @var array $scriptProperties */
/* @var miniShop2 $miniShop2 */
$miniShop2 = $modx->getService('minishop2');
$miniShop2->initialize($modx->context->key);
/* @var pdoFetch $pdoFetch */
if (!$modx->loadClass('pdofetch', MODX_CORE_PATH . 'components/pdotools/model/pdotools/', false, true)) {return false;}
$pdoFetch = new pdoFetch($modx, $scriptProperties);

if (!empty($product)) {
	$product = $modx->getObject('msProduct', $product);
}
else {
	$product = $modx->resource;
}

if (!($product instanceof msProduct)) {return 'This resource is not instance of msProduct class.';}
$limit = !empty($scriptProperties['limit']) ? $scriptProperties['limit'] : 0;
$where = array(
	'product_id' => $product->get('id')
	,'parent' => 0
	,'type' => 'image'
);
// processing additional query params
if (!empty($scriptProperties['where'])) {
	$tmp = $modx->fromJSON($scriptProperties['where']);
	if (is_array($tmp) && !empty($tmp)) {
		$where = array_merge($where, $tmp);
	}
}
unset($scriptProperties['where']);

// Default parameters
$default = array(
	'class' => 'msProductFile'
	,'where' => $modx->toJSON($where)
	,'select' => '{"msProductFile":"all"}'
	,'limit' => $limit
	,'sortby' => 'rank'
	,'sortdir' => 'ASC'
	,'fastMode' => false
	,'return' => 'data'
	,'nestedChunkPrefix' => 'minishop2_'
);

// Merge all properties and run!
$pdoFetch->setConfig(array_merge($default, $scriptProperties));
$rows = $pdoFetch->run();

// Processing rows
$output = null; $images = array();
$idx = !empty($scriptProperties['offset']) ? $scriptProperties['offset'] : 0;
$pdoFetch->addTime('Fetching thumbnails');
foreach ($rows as $k => $row) {
	$idx++;
	$row['idx'] = $idx;
	$images[$row['id']] = $row;
	$q = $modx->newQuery('msProductFile', array('parent' => $row['id']));
	$q->select('url');
	if ($q->prepare() && $q->stmt->execute()) {
		while ($tmp = $q->stmt->fetch(PDO::FETCH_COLUMN)) {
			if (preg_match('/((?:\d{1,4}|)x(?:\d{1,4}|))/', $tmp, $size)) {
				$images[$row['id']][$size[0]] = $tmp;
			}
		}
	}
}

// Processing chunks
$pdoFetch->addTime('Processing chunks');
$rows = array();
foreach ($images as $row) {
	$rows[] = empty($tplRow)
		? $pdoFetch->getChunk('', $row)
		: $pdoFetch->getChunk($tplRow, $row, $pdoFetch->config['fastMode']);
}

$pdoFetch->addTime('Returning processed chunks');
if (!empty($rows)) {
	$output = implode($pdoFetch->config['outputSeparator'], $rows);
}

// Return output
if (!empty($output)) {
	if (!empty($tplOuter)) {
		$output = $pdoFetch->getChunk($tplOuter, array('rows' => $output));
	}
}
else {
	$output = !empty($tplEmpty) ? $pdoFetch->getChunk($tplEmpty) : '';
}

if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
	$output .= '<pre class="msGalleryLog">' . print_r($pdoFetch->getTime(), 1) . '</pre>';
}

if (!empty($toPlaceholder)) {
	$modx->setPlaceholder($toPlaceholder, $output);
}
else {
	return $output;
}