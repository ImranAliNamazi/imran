<?php
//retain .txt and use .md for the deep dives

function before_file() {
	if (variable('hasPiece')) {
		printPiece(variable('currentPiece'), 'before');
	} if (variable('hasPieces')) {
		$count = count(variable('currentPieces'));
		foreach (variable('currentPieces') as $ix => $item)
			printPiece($item, 'during', $ix + 1 . '/' . $count);
	}
}

function after_file() {
	if (variable('hasPiece')) {
		$current = variable('currentPiece');

		if ($item = variable('nextPiece'))
			printPiece($item, 'after', false, 'Next');
		if ($item = variable('previousPiece'))
			printPiece($item, 'after', false, 'Previous');

		$md = str_replace('.txt', '.md', $current['File']);
		if (disk_file_exists($md)) {
			contentBox('', 'container standout');
			printH1InDivider('Deep Dive with Copilot');
			variable('no-content-boxes', true);
			autoRender($md, 'engage');
			clearVariable('no-content-boxes');
			contentBox('end');
		}
	}
}

function printPiece($item, $where, $xofy = false, $relative = '') {
	contentBox('', 'container');
	if ($relative) echo '<span class="right-button">' . $relative . '</span>';

	$heading = $item['SNo'] . '. ' . ($name = $item['Name']);
	if ($where != 'before') $heading = getLink($heading, urlFromSlugs($item['Name']));
	h2($heading, 'm-0 p-0');

	$name_websafe = urlize($name);
	$url = pageUrl($name);

	echo replaceItems('Dedicated To: %Dedication%, %Date%%Position%<br />Category: %Category%, Collection: %Collection%', [
		'Dedication' => getLink($item['Dedication'], urlFromSlugs($item['Type'], 'for', $item['Dedication'])),
		'Date' => $item['Date'],
		'Position' => $xofy ? ' <span style="float: right">' . $xofy . '</span>' : '',
		'Category' => getLink($item['Category'], urlFromSlugs($item['Type'], 'category', $item['Category'])),
		'Collection' => getLink($item['Collection'], urlFromSlugs('collections', $item['Collection'])),
	], '%');

	echo BRNL . '<p class="mt-3 p-3 content-box after-content">' . $item['Description'] . '</p>';

	//TODO: if matching image

	contentBox('end');
}

function did_site_render_page() {

}

variables([
	'link-to-section-home' => true,
]);

function getEnrichedPieceObj($item, $sheet) {
	$result = rowToObject($item, $sheet);
	$result['Type'] = $type = 'poems'; //todo: high work type from a collate of 3 menu items
	$result['File'] = concatSlugs([variable('path'), $type,
		$sheet->getValue($item, 'Collection'),
		urlize($sheet->getValue($item, 'Name')) . '.txt',
	]);
	return $result;
}

function beforeSectionSet() {
	$node = variable('node');
	$piece = in_array($node, ['all', 'poems', 'prose', 'essays']);
	$alias = in_array($node, ['works', 'collections']);

	$byWork = getSheet('sitemap', 'Work');

	if (!$piece && !$alias) {
		$sheet = getSheet('sitemap', 'Name');
		$name = humanize($node);
		if (!isset($sheet->group[$name]))
		return false;

		$item = $sheet->group[$name][0];

		$ofSameWork = $byWork->group[$sheet->getValue($item, 'Work')];
		$indicesByName = [];
		foreach ($ofSameWork as $ix => $row) $indicesByName[$byWork->getValue($row, 'Name')] = $ix;

		$currentIndex = $indicesByName[$sheet->getValue($item, 'Name')];
		$current = getEnrichedPieceObj($item, $sheet);
		$previous = $currentIndex > 0 ? getEnrichedPieceObj($ofSameWork[$currentIndex - 1], $sheet) : false;
		$next = $currentIndex < count($ofSameWork) -1 ? getEnrichedPieceObj($ofSameWork[$currentIndex + 1], $sheet) : false;

		variables([
			'file' => $current['File'],
			'hasPiece' => true,
			'currentPiece' => $current,
			'previousPiece' => $previous,
			'nextPiece' => $next,
		]);

		afterSectionSet();
		return true;
	}

	if ($node == 'collections')
		$sheet = getSheet('sitemap', 'Collection');
	else if ($node == 'works')
		$sheet = $byWork;

	$on = getPageParameterAt(1);
	if (!isset($sheet->group[$on]))
		return false;

	$items = $sheet->group[$on];
	$pieces = [];
	foreach ($items as $item)
		$pieces[] = getEnrichedPieceObj($item, $sheet);

	variables([
		'hasPieces' => true,
		'currentPieces' => $pieces,
	]);

	afterSectionSet();
	return true;
}

function getParentSlug($sectionFor) {
	$piece = in_array($sectionFor, ['poems', 'prose', 'essays']);
	if ($piece) return $sectionFor;
	
	return '';
}

function getParentSlugForMenuItem($sectionFor, $item) {
	$piece = in_array($sectionFor, ['poems', 'prose', 'essays']);
	if ($piece) return 'collections/';

	$alias = in_array($sectionFor, ['works']);
	if ($alias) return 'works';
	
	return '';
};
