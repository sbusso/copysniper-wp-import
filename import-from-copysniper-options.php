<?php

function import_from_copysniper_get_options() {
	$defaults = array(
		'root_directory' => '/home/public_html/wp-content/uploads',
		'old_url' => '',
		'index_file' => 'index.html',
		'copysniper_file' => 'wp.html',
		'file_extensions' => 'html,htm,shtml',
		'skipdirs' => __('', 'import-from-copysniper'),
		'preserve_slugs' => 0,
		'status' => 'draft',
		'root_parent' => 0,
		'type' => 'page',
		'timestamp' => 'filemtime',
		'import_content' => 'file',
		'content_region' => '',
		'content_tag' => __('div', 'import-from-copysniper'),
		'content_tagatt' => __('id', 'import-from-copysniper'),
		'content_attval' => __('content', 'import-from-copysniper'),
		'clean_html' => 1,
		'encode' => 1,
		'allow_tags' => '<p><br><img><a><ul><ol><li><dl><dt><dd><blockquote><cite><em><i><strong><b><h2><h3><h4><h5><h6><hr>',
		'allow_attributes' => 'href,alt,title,src',
		'import_images' => 0,
		'import_documents' => 0,
		'document_mimes' => 'rtf,doc,docx,xls,xlsx,csv,ppt,pps,pptx,ppsx,pdf,zip,wmv,avi,flv,mov,mpeg,mp3,m4a,wav',
		'fix_links' => 1,
		'import_title' => 'tag',
		'title_region' => '',
		'title_tag' => __('title', 'import-from-copysniper'),
		'title_tagatt' => '',
		'title_attval' => '',
		'remove_from_title' => '',
		'title_inside' => 0,
		'meta_desc' => 1,
		'user' => 0,
		'page_template' => 0,
		'firstrun' => true,
		'import_date' => 0,
		'date_region' => '',
		'date_tag' => __('div', 'import-from-copysniper'),
		'date_tagatt' => __('id', 'import-from-copysniper'),
		'date_attval' => __('date', 'import-from-copysniper'),
		'import_field' => array('0'),
		'customfield_name' => array(''),
		'customfield_region' => array(''),
		'customfield_tag' => array(__('div', 'import-from-copysniper')),
		'customfield_tagatt' => array(__('class', 'import-from-copysniper')),
		'customfield_attval' => array(__('fieldclass', 'import-from-copysniper'))
	);
	$options = get_option('import_from_copysniper');
	if (!is_array($options)) $options = array();
	return array_merge( $defaults, $options );
}

?>