<?php
//retain .txt and use .md for the deep dives

function did_site_render_page() {
	if (variable('hasPiece')) {
		renderAny(variable('file'));
		contentBox('end');
	}

	return variable('hasPiece') || variable('hasPieces');
}

function before_file() {
	if (variable('hasPiece')) {
		printPiece(variable('currentPiece'), 'before');
	} else if (variable('hasPieces')) {
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

	$heading = '<!--noop-->' . $item['SNo'] . '. ' . ($name = $item['Name']);
	if ($where != 'before') $heading = getLink($heading, urlFromSlugs($item['Name']));

	if ($xofy) echo '<span style="float: right">' . $xofy . '</span>';
	h2($heading, 'm-0 p-0');

	$name_websafe = urlize($name);
	$url = pageUrl($name);

	echo '<p class="mt-2 mb-3 p-3 content-box after-content">' . $item['Description'] . '</p>';

	echo '<div class="large-list with-labels"><ul class="p-0"><li>' . NEWLINE . implode('</li>' . NEWLINE . '	<li>', [
		'<label>Date: </label> '       . $item['Date'],
		'<label>Category: </label> '   . getLink(_getTaxonomyText($item['Category'], 'category'), urlFromSlugs('categories', $item['Category'])),
		'<label>Dedication: </label> ' . getLink($item['Dedication'], urlFromSlugs('for', $item['Dedication'])),
		'<label>Collection: </label> ' . getLink(_getTaxonomyText($item['Collection'], 'collection'), urlFromSlugs('collections', $item['Collection'])),
		'<label>Work: </label> ' . getLink(_getTaxonomyText($item['Work'], 'work'), urlFromSlugs('works', $item['Work'])),
		'<label>Rhymes: </label> ' . $item['RhymeScheme'],
	]) . NEWLINE . '</li></div>' . NEWLINE;

	//TODO: if matching image / meta

	if ($where != 'before')
		contentBox('end');
}

function _getTaxonomyText($val, $type) {
	$sheet = getSheet('taxonomy', 'type');
	$groupKey = 'types_' . $type;
	if (!($group = variable($groupKey))) {
		$group = arrayGroupBy($sheet->group[$type], $sheet->columns['slug'], true);
		variable($groupKey, $group);
	}

	$key = urlize($val);
	if (!isset($group[$key])) return $val;

	$item = $group[$key][0];
	return $sheet->getValue($item, 'text');
}

function site_before_render() {
	$section = variable('section');
	$node = variable('node');

	if (	!in_array($section, ['grow', 'more', 'with-ai'])
		&&	!in_array($section, ['2020', '2021', 'archives', 'ideas', 'ideas2']) )
		return;

	if ($section == $node) return;

	DEFINE('NODEPATH', SITEPATH . '/' . variable('section') . '/' . $node);
	variables([
		'nodeSiteName' => humanize($node),
		'nodeSafeName' => $node,
		'submenu-at-node' => true,
	]);
}

variables([
	//'link-to-section-home' => true,
]);

function getEnrichedPieceObj($item, $sheet) {
	$result = rowToObject($item, $sheet);
	$result['Type'] = $type = _getWorkType($result);
	$result['File'] = concatSlugs([variable('path'), $type . ($type == 'prose' ? '/' . urlize($result['Work']) : ''),
		$sheet->getValue($item, 'Collection'),
		urlize($sheet->getValue($item, 'Name')) . '.txt',
	]);
	return $result;
}

function _getWorkType($item) {
	//TODO: high work type from a collate of 3 menu items
	$prose = ['daivic', 'essays', 'reviews', 'touched-by-grace'];
	return in_array($item['Work'], $prose) ? 'prose' : 'poems';
}

function beforeSectionSet() {
	$node = variable('node');
	$piece = in_array($node, ['all', 'poems', 'prose']);
	$alias = in_array($node, ['works', 'collections', 'categories', 'for']);

	$byWork = getSheet('sitemap', 'Work');

	if (!$piece && !$alias) {
		$sheet = getSheet('sitemap', 'Name', true);

		if (!isset($sheet->group[$node]))
			return false;

		$item = $sheet->group[$node][0];

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

	if ($node == 'works')
		$sheet = $byWork;
	else if ($node == 'collections')
		$sheet = getSheet('sitemap', 'Collection');
	else if ($node == 'categories')
		$sheet = getSheet('sitemap', 'Category', true);
	else if ($node == 'for')
		$sheet = getSheet('sitemap', 'Dedication', true);
	else if ($piece)
		$sheet = getSheet('sitemap', false);

	$on = getPageParameterAt(1);

	if ($alias && !isset($sheet->group[$on]))
		return false;

	$items = $piece ? $sheet->rows : $sheet->group[$on];
	$pieces = [];
	foreach ($items as $item) {
		$obj = getEnrichedPieceObj($item, $sheet);
		if ($piece && $node != 'all' && $node != $obj['Type']) continue;
		$pieces[] = $obj;
	}

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
