<?php
if ( !defined('WP_LOAD_IMPORTERS') )
	return;

// load importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}

if( class_exists( 'WP_Importer' ) ) {
class Copysniper_Import extends WP_Importer {

	var $posts = array ();
	var $file;

	function header() {
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>'.__('Import from Copysniper', 'import-from-copysniper').'</h2>';
	}

	function footer() {
		echo '</div>';
	}
	
	function greet() {
		$options = get_option('import_from_copysniper'); ?>
		
		<form enctype="multipart/form-data" method="post" action="" id="upload_file">
			<label>Choose a zip file to upload:
				<input type="file" name="zip_file" />
				<input type="submit" name="submit" value="Upload" class="button-secondary" />
			</label>
		</form>
		
		<!--<p><b>Please note:</b> zip file must have <em>exactly</em> the same name as the folder you're uploading. For example:</p>
		<p style="margin-left: 15px;">If the folder is called <span style="color: #0000ff;">my-folder</span>, the zip file must be called <span style="color: #0000ff;">my-folder.zip</span>.</p>-->
		
		<?php 
		$this->upload_file();
		?>
		
		<?php
		$success = $this->uploadStatus;
		if( isset( $success ) ) {
			//echo '$import_path = ' . $this->importPath;
			echo '<style>#upload_file { display: none; }</style>';
			echo '<h3>Importing files...</h3>';
		?>
			
			<script type="text/javascript">
				function submitForm() {
					setTimeout(
						function()
						{
						document.getElementById('importForm').submit();
						}, 3000);
				}
			</script>
			
			<form id="importForm" enctype="multipart/form-data" method="post" action="admin.php?import=copysniper&amp;step=1">
				<input type="hidden" name="import_path" value="<?php echo $this->importPath; ?>" />
				<input type="hidden" name="import_url" value="<?php echo $this->importURL; ?>" />
				<noscript><input type="submit" name="submit" class="button" value="<?php echo esc_attr(__('Continue', 'import-from-copysniper')); ?>" /></noscript>
				
				<?php wp_nonce_field('import_from_copysniper'); ?><!-- security check -->
			</form>
			
			<script type="text/javascript">
				submitForm();
			</script>
		
		<?php
		} // end if
	}
	
	function upload_file() {		
		if( isset( $_FILES["zip_file"]["name"] ) ) {
			$filename = $_FILES["zip_file"]["name"];
			$source = $_FILES["zip_file"]["tmp_name"];
			$type = $_FILES["zip_file"]["type"];
			
			$name = substr($filename, -3);
			$accepted_types = array('application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/x-compressed');
			foreach($accepted_types as $mime_type) {
				if($mime_type == $type) {
					$okay = true;
					break;
				} 
			}
			
			$continue = strtolower($name);
			if($continue != 'zip') {
				$fail = '<h3 style="color: #ff0000;";>The file you are trying to upload is not a zip file. Please try again.</h3>';
			}
			
			else {
				$target_path = "../wp-content/uploads/".$filename; // set correct path
				if(move_uploaded_file($source, $target_path)) {
					$zip = new ZipArchive();
					$x = $zip->open($target_path);
					if ($x === true) {
						$zip->extractTo("../wp-content/uploads"); // set correct path
						$zip->close();
				
						unlink($target_path);
						
						$target_path = str_replace('.zip', '', $target_path); // remove '.zip'
						//$target_path = preg_replace('/^([^_]*).*$/', '$1', $target_path); // remove everything after first '_'
						$this->importURL = $target_path; // set URL for fixing links
						
						$import_path = str_replace('../', ABSPATH, $target_path); // change path
						$this->importPath = $import_path; // set path for uploaded files
					}
					$success = '<p>Your file was uploaded and unpacked successfully.</p>';
				} 
				else {	
					$fail = '<h3 style="color: #ff0000;";>There was a problem with the upload. Please <a href="admin.php?import=copysniper">try again</a>.</h3>';
				}
			}
		}
		
		if( isset( $success ) ) {
			echo $success;
			$this->uploadStatus = $success;
		}
		elseif( isset( $fail ) ) {
			echo $fail;
		}
	}
	
	function update_root_directory() { // not used
		$options = get_option('import_from_copysniper');
		$import_path = $this->importPath;
		$options['root_directory'] = $import_path;
		update_option( 'import_from_copysniper', $options );
	}
	
	function fix_hierarchy($postid, $path) {
		$options = get_option('import_from_copysniper');
		$parentdir = rtrim($this->parent_directory($path), '/');
		
		// create array of parent directories, starting with the index file's parent and moving up to the root directory
		while ($parentdir != /*$options['root_directory']*/ $this->importPath) {
			$parentarr[] = $parentdir;
			$parentdir = rtrim($this->parent_directory($parentdir), '/');
		}
		// reverse the array so we start at the root -- this way the parents can be found when we search in $this->get_post
		$parentarr = array_reverse($parentarr);
		
		//echo '<pre>'.print_r($parentarr, true).'</pre>';
		
		foreach ($parentarr as $parentdir) {
			$parentID = array_search($parentdir, $this->filearr);
			if ($parentID === false)
				$this->get_post($parentdir, true);
		}
		
		// now fix the parent ID of the original index file (in $postid)
		// it's the next to last element in the array we want. (The last one is the index file.) If this doesn't exist, we don't need to fix the parent.
		$grandparent = count($parentarr)-2;
		if (isset($parentarr[$grandparent])) {
			$parentdir = $parentarr[$grandparent];
			$my_post['ID'] = $postid;
			$my_post['post_parent'] = array_search($parentdir, $this->filearr);
		
			//echo "\n<pre>The parent of $postid should be ".$my_post['post_parent']."</pre>"; 
		
			if (!empty($my_post['post_parent']))
				wp_update_post( $my_post );
		}
	}
	
	function parent_directory($path) {
		$win = false;
		if (strpos($path, '\\') !== FALSE) {
			$win = true;
	    	$path = str_replace('\\', '/', $path);
		}
	    if (substr($path, strlen($path) - 1) != '/') $path .= '/'; 
	    $path = substr($path, 0, strlen($path) - 1);
	    $path = substr($path, 0, strrpos($path, '/')) . '/';
	    if ($win) $path = str_replace('/', '\\', $path);
	    return $path;
	}
	
	function fix_internal_links($content, $id) {
		// find all href, src and image attributes
		preg_match_all('/( href=| src=|image:)[\'"]([^>\'" ]+)/i', $content, $matches);
		for ($i=0; $i<count($matches[1]); $i++) {
			$hrefs[] = $matches[2][$i];
			$hrefs = array_unique($hrefs);
		}
		if (!empty($hrefs)) {
			//print_r($hrefs);
			//echo '<p>Looking in '.get_permalink($id).'</p>';
			foreach ($hrefs as $href) {
				if ('#' != substr($href, 0, 1) && 'mailto:' != substr($href, 0, 7)) { // skip anchors and mailtos
					// relative paths
					if ('/' == substr($href, 0, 1)) {
						$linkpath = $this->importURL . $href;
						$linkpath = $this->remove_dot_segments($linkpath);
						$newlink = home_url() . '/' . $linkpath;
					}
					else {
						$newlink = $href;
					}
					
					$newlink = rtrim($newlink, '/');
					//echo '<br>Old link: '.$href.' -> New link: '.$newlink;
					$content = str_replace($href, $newlink, $content);
				} // if #/mailto
			} // foreach
		} // if empty
		return $content;
	}
	
	function remove_dot_segments( $path ) {
		$inSegs  = preg_split( '!/!u', $path );
		$outSegs = array( );
		foreach ( $inSegs as $seg )
		{
		    if ( empty( $seg ) || $seg == '.' )
		        continue;
		    if ( $seg == '..' )
		        array_pop( $outSegs );
		    else
		        array_push( $outSegs, $seg );
		}
		$outPath = implode( '/', $outSegs );
		if ( isset($path[0]) && $path[0] == '/' )
		    $outPath = '/' . $outPath;
		if ( $outPath != '/' &&
		    (mb_strlen($path)-1) == mb_strrpos( $path, '/', 'UTF-8' ) )
		    $outPath .= '/';
		$outPath = str_replace('http:/', 'http://', $outPath);
		$outPath = str_replace('https:/', 'https://', $outPath);
		$outPath = str_replace(':///', '://', $outPath);
		return rawurldecode($outPath);
	}
	
	function clean_html( $string, $allowtags = NULL, $allowattributes = NULL ) {
		// from: http://us3.php.net/manual/en/function.strip-tags.php#91498
	    /*$string = strip_tags($string,$allowtags);
	    if (!is_null($allowattributes)) {
	        if(!is_array($allowattributes))
	            $allowattributes = explode(",",$allowattributes);
	        if(is_array($allowattributes))
	            $allowattributes = implode(")(?<!",$allowattributes);
	        if (strlen($allowattributes) > 0)
	            $allowattributes = "(?<!".$allowattributes.")";
	        $string = preg_replace_callback("/<[^>]*>/i",create_function(
	            '$matches',
	            'return preg_replace("/ [^ =]*'.$allowattributes.'=(\"[^\"]*\"|\'[^\']*\')/i", "", $matches[0]);'   
	        ),$string);
	    }*/
		
		// reduce line breaks and remove empty tags
		$string = str_replace( '\n', ' ', $string ); 
		$string = preg_replace( "/<[^\/>]*>([\s]?)*<\/[^>]*>/", ' ', $string );
		
		// get rid of remaining newlines; basic HTML cleanup
		$string = str_replace('&#13;', ' ', $string);
		$string = ereg_replace("[\n\r]", " ", $string); 
		$string = preg_replace_callback('|<(/?[A-Z]+)|', create_function('$match', 'return "<" . strtolower($match[1]);'), $string);
		$string = str_replace('<br>', '<br />', $string);
		$string = str_replace('<hr>', '<hr />', $string);
		return $string;
	}
	
	function handle_accents() {
		// from: http://www.php.net/manual/en/domdocument.loadhtml.php#91513
		$content = $this->file;
		if (!empty($content) && function_exists('mb_convert_encoding')) {
			mb_detect_order("ASCII,UTF-8,ISO-8859-1,windows-1252,iso-8859-15");
            if (empty($encod))
                $encod = mb_detect_encoding($content);
            	$headpos = mb_strpos($content,'<head>');
            if (FALSE === $headpos)
                $headpos= mb_strpos($content,'<HEAD>');
            if (FALSE !== $headpos) {
                $headpos+=6;
                $content = mb_substr($content,0,$headpos) . '<meta http-equiv="Content-Type" content="text/html; charset='.$encod.'">' .mb_substr($content,$headpos);
            }
            $content = mb_convert_encoding($content, 'HTML-ENTITIES', $encod);
        }
		return $content;
	}
	
	function get_files_from_directory($rootdir) {
		$options = get_option('import_from_copysniper');
		
		if(is_dir($rootdir)) { // check directory is valid
			$dir_content = scandir($rootdir);
			foreach($dir_content as $key => $val) {
			  set_time_limit(30);
			  $path = $rootdir.'/'.$val;
			  if(is_file($path) && is_readable($path) && ($val == $options['copysniper_file'])) { // only import Copysniper HTML file
				$filename_parts = pathinfo($path);
				$ext = '';
				if (isset($filename_parts['extension']))
					$ext = strtolower($filename_parts['extension']);
				// allowed extensions only, please
				if (!empty($ext) && in_array($ext, $this->allowed)) {
					if (filesize($path) > 0) {  // silently skip empty files
						// read the HTML file
						$contents = @fopen($path);  // read entire file
						if (empty($contents)) 
							$contents = @file_get_contents($path); 
						if (!empty($contents)) {	// silently skip files we can't open	
							$this->file = $contents;
							$this->get_post($path, false); // import the post
						}
					}
				}
			  }
			} // end foreach
		}
		else {
			echo '<h3 style="color: #ff0000;";>There was a problem importing your files. Please check the <a href="http://copysniper.com/" target="_blank">User Guide</a> and <a href="admin.php?import=copysniper">try again</a>.</h3>';
			exit;
		}
	}
	
	
	
	// this is where the magic happens
	function get_post($path = '', $placeholder = false) {
		// this gets the content AND imports the post because we have to build $this->filearr as we go so we can find the new post IDs of files' parent directories
		set_time_limit(540);
		$options = get_option('import_from_copysniper');
		$updatepost = false;
		
		if ($placeholder) {
			$title = trim(strrchr($path,'/'),'/');
			$title = str_replace('_', ' ', $title);
			$title = str_replace('-', ' ', $title);
			$my_post['post_title'] = ucwords($title);
			
			if (isset($options['preserve_slugs']) && '1' == $options['preserve_slugs']) {
				$filename = basename($path);
				$my_post['post_name'] = substr($filename,0,strrpos($filename,'.'));
			}
			
			if ($options['timestamp'] == 'filemtime')
				$date = filemtime($path);
			else $date = time();
			$my_post['post_date'] = date("Y-m-d H:i:s", $date);
			$my_post['post_date_gmt'] = date("Y-m-d H:i:s", $date);

			$my_post['post_type'] = $options['type'];

			$parentdir = rtrim($this->parent_directory($path), '/');
			
			$my_post['post_parent'] = array_search($parentdir, $this->filearr);
			if ($my_post['post_parent'] === false)
				$my_post['post_parent'] = $options['root_parent'];

			$my_post['post_content'] = '<!-- placeholder -->';
			$my_post['post_status'] = $options['status'];
			$my_post['post_author'] = $options['user'];
		}
		else {
			set_magic_quotes_runtime(0);
			$doc = new DOMDocument();
			$doc->strictErrorChecking = false; // ignore invalid HTML, we hope
			$doc->preserveWhiteSpace = false;  
			$doc->formatOutput = false;  // speed this up
			if (!empty($options['encode'])) {  // we have to deal with character encoding BEFORE calling loadHTML() - eureka!
				$content = $this->handle_accents();
				@$doc->loadHTML($content);
			}
			else
				@$doc->loadHTML($this->file);
			$xml = @simplexml_import_dom($doc);
			// avoid asXML errors when it encounters character range issues
			libxml_clear_errors();
			libxml_use_internal_errors(false);
			
			// start building the WP post object to insert
			$my_post = array();
			
			// title
			if ($options['import_title'] == "filename") {
				$path_split = explode('/',$path);
				$file_name = trim(end($path_split));
				$file_name = preg_replace('/\.[^.]*$/', '', $file_name); // remove extension
				$parent_directory = trim(prev($path_split));
				
				if(basename($path) == $options['index_file']) {
					$title = $parent_directory;
				} else {
					$title = $file_name;
				}
				$title = str_replace('_', ' ', $title);
				$title = str_replace('-', ' ', $title);
				$my_post['post_title'] = ucwords($title);
			}
			else { // it's a tag
				$titletag = $options['title_tag'];
				$titletagatt = $options['title_tagatt'];
				$titleattval = $options['title_attval'];
				$titlequery = '//'.$titletag;
				if (!empty($titletagatt))
					$titlequery .= '[@'.$titletagatt.'="'.$titleattval.'"]';
					$title = $xml->xpath($titlequery);
						if (isset($title[0]))
							$title = $title[0]->asXML(); // asXML() preserves HTML in content
						else { // fallback
							$title = $xml->xpath('//title');
							if (isset($title[0]))
								$title = $title[0];
							if (empty($title))
								$title = '';
							else
								$title = (string)$title;
						}
						// last resort: filename
						if (empty($title)) {
							$path_split = explode('/',$path);
							$title = trim(end($path_split));
						}	
						$title = str_replace('<br>',' ',$title);
						$my_post['post_title'] = trim(strip_tags($title));
			}
			
			$remove = $options['remove_from_title'];
			if (!empty($remove))
				$my_post['post_title'] = str_replace($remove, '', $my_post['post_title']);
			
			//echo '<pre>'.$my_post['post_title'].'</pre>'; exit;
			
			// slug
			if (isset($options['preserve_slugs']) && '1' == $options['preserve_slugs']) {
				// there is no path when we're working with a single uploaded file instead of a directory
				if (empty($path)) 
					$filename = $this->filename;
				else
					$filename = basename($path);
				$my_post['post_name'] = substr($filename,0,strrpos($filename,'.'));
			}
			
			// post type
			$my_post['post_type'] = $options['type'];
		
			if (is_post_type_hierarchical($my_post['post_type'])) {
				if (empty($path)) 
					$my_post['post_parent'] = $options['root_parent'];
				else {
					$parentdir = rtrim($this->parent_directory($path), '/');
					$my_post['post_parent'] = array_search($parentdir, $this->filearr);
					if ($my_post['post_parent'] === false)
						$my_post['post_parent'] = $options['root_parent'];
				}
			}
		
			// date
			if ($options['timestamp'] == 'filemtime' && !empty($path)) {
				$date = filemtime($path);
				$my_post['post_date'] = date("Y-m-d H:i:s", $date);
				$my_post['post_date_gmt'] = date("Y-m-d H:i:s", $date);
			}
			else if ( $options['timestamp'] == 'customfield' ) {
				$tag = $options['date_tag']; // it's a tag
				$tagatt = $options['date_tagatt'];
				$attval = $options['date_attval'];
				$xquery = '//'.$tag;
					if (!empty($tagatt))
						$xquery .= '[@'.$tagatt.'="'.$attval.'"]';
						$date = $xml->xpath($xquery);
					if (is_array($date) && isset($date[0]) && is_object($date[0])) {
						if (isset($date[0]))
							$stripdate = $date[0]->asXML(); // asXML() preserves HTML in content
							$date = strip_tags($date[0]);
							$date = strtotime($date);
							//echo $date; exit;
					}
					else { // fallback 
						$date = time();
					}
			}
			else {
			 	$date = time();
			}
			$my_post['post_date'] = date("Y-m-d H:i:s", $date);
			$my_post['post_date_gmt'] = date("Y-m-d H:i:s", $date);

			// content
			if ( $options['import_content'] == "file" ) { // import entire file (works well)
				$my_post['post_content'] = $this->file;
			}
			else { // it's a tag (doesn't work well)
				$tag = $options['content_tag'];
				$tagatt = $options['content_tagatt'];
				$attval = $options['content_attval'];
				
				$xquery = '//'.$tag; // e.g. //id
				if (!empty($tagatt))
					$xquery .= '[@'.$tagatt.'="'.$attval.'"]'; // e.g. id=content
					$content = $xml->xpath($xquery);
					if (is_array($content) && isset($content[0]) && is_object($content[0]))
						$my_post['post_content'] = $content[0]->asXML(); // asXML() preserves HTML in content
					else {  // fallback
						$content = $xml->xpath('//body');
						if (is_array($content) && isset($content[0]) && is_object($content[0]))
							$my_post['post_content'] = $content[0]->asXML();
						else
							$my_post['post_content'] = '';
					}
			}
			
			if ($options['title_inside'])
				$my_post['post_content'] = str_replace($title, '', $my_post['post_content']);
			
			if (!empty($my_post['post_content'])) {
				if (!empty($options['clean_html']))
					$my_post['post_content'] = $this->clean_html($my_post['post_content'], $options['allow_tags'], $options['allow_attributes']);
			}
			
			// custom fields
			$customfields = array();
			foreach ($options['customfield_name'] as $index => $fieldname) {
				if (!empty($fieldname)) { // it's a tag
					$tag = $options['customfield_tag'][$index];
					$tagatt = $options['customfield_tagatt'][$index];
					$attval = $options['customfield_attval'][$index];
					$xquery = '//'.$tag;
					if (!empty($tagatt))
						$xquery .= '[@'.$tagatt.'="'.$attval.'"]';
						$content = $xml->xpath($xquery);
						if (is_array($content) && isset($content[0]) && is_object($content[0]))
							$customfields[$fieldname] = strip_tags($content[0]);
				}
			}

			// excerpt
			$excerpt = $options['meta_desc'];
			if (!empty($excerpt)) {
				$my_post['post_excerpt'] = $xml->xpath('//meta[@name="description"]');
				if (isset($my_post['post_excerpt'][0]))
					$my_post['post_excerpt'] = $my_post['post_excerpt'][0]['content'];
				if (is_array($my_post['post_excerpt']))
					$my_post['post_excerpt'] = implode('',$my_post['post_excerpt']);
				$my_post['post_excerpt'] = (string)$my_post['post_excerpt'];
			}
			
			// status
			$my_post['post_status'] = $options['status'];
			
			// author
			$my_post['post_author'] = $options['user'];
		}
		
		// if it's a single file, we can use a substitute for $path from here on
		if (empty($path)) $handle = __("the uploaded file", 'import-from-copysniper');
		else $handle = $path;
		
		// see if the post already exists
		// but don't bother printing this message if we're doing an index file; we know its parent already exists
		if ($post_id = post_exists($my_post['post_title'], $my_post['post_content'], $my_post['post_date']) && basename($path) != $options['index_file'])
			$this->table[] = "<tr><th class='error'>--</th><td colspan='3' class='error'> " . sprintf(__("%s (%s) has already been imported", 'import-from-copysniper'), $my_post['post_title'], $handle) . "</td></tr>";
		
		// if we're doing hierarchicals and this is an index file of a subdirectory, instead of importing this as a separate page, update the content of the placeholder page we created for the directory
		$index_files = explode(',',$options['index_file']);
		if (is_post_type_hierarchical($options['type']) && dirname($path) != /*$options['root_directory']*/ $this->importPath && in_array(basename($path), $index_files) ) {
			$post_id = array_search(dirname($path), $this->filearr);
			if ($post_id !== 0)
				$updatepost = true;
		}
		
		if ($updatepost) { 
			$my_post['ID'] = $post_id; 
			wp_update_post( $my_post );
		}
		else // insert new post
			$post_id = wp_insert_post($my_post);
		
		// handle errors
		if ( is_wp_error( $post_id ) )
			$this->table[] = "<tr><th class='error'>--</th><td colspan='3' class='error'> " . $post_id /* error msg */ . "</td></tr>";
		if (!$post_id) 
			$this->table[] = "<tr><th class='error'>--</th><td colspan='3' class='error'> " . sprintf(__("Could not import %s. You should copy its contents manually.", 'import-from-copysniper'), $handle) . "</td></tr>";
		
		// if no errors, handle custom fields
		if (isset($customfields)) {
			foreach ($customfields as $fieldname => $fieldvalue) {
				// allow user to set tags via custom field named 'post_tag'
				if ($fieldname == 'post_tag')
					$customfieldtags = $fieldvalue;
				else
					add_post_meta($post_id, $fieldname, $fieldvalue, true);
			}
		}
		
		// ... and all the taxonomies...
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects', 'and' );
		foreach ( $taxonomies as $tax ) {
			if (isset($options[$tax->name]))
				wp_set_post_terms( $post_id, $options[$tax->name], $tax->name, false);
		}
		if (isset($customfieldtags))
			wp_set_post_terms( $post_id, $customfieldtags, 'post_tag', false);
		
		// ...and set the page template, if any
		if (isset($options['page_template']) && !empty($options['page_template']))
			add_post_meta($post_id, '_wp_page_template', $options['page_template'], true);
		
		// store path so we can check for parents later (even if it's empty; need that info for image imports). 
		// Don't store the index file updates; they'll screw up the parent search, and they can use their parents' path anyway
		if (!$updatepost)
			$this->filearr[$post_id] = $path;
		else {  // index files will have an incomplete hierarchy if there were empty directories in their path
			$this->fix_hierarchy($post_id, $path);
		}
		
		// create the results table row AFTER fixing hierarchy
		if (!empty($path)) {
			if (empty($my_post['post_title']))
				$my_post['post_title'] = __('(no title)', 'import-from-copysniper');
				$this->table[$post_id] = '<tr><th>' . $post_id . '</th><td><a href="post.php?action=edit&post=' . $post_id . '">' . esc_html($my_post['post_title']) . '</a></td></tr>';
		}
		else {
			$this->single_result = sprintf( __('Imported the file as %s.', 'import-from-copysniper'), '<a href="post.php?action=edit&post=' . $post_id . '">' . $my_post['post_title'] . '</a>');
		}
	} // end get_post
	
	function find_internal_links() {
		$options = get_option('import_from_copysniper');
		$posttype = $options['type'];
		
		echo '<br />';
		echo '<h3>'.__( 'Fixing relative links...', 'import-from-copysniper').'</h3>';
		echo '<p>'.__( 'The importer is searching your imported pages for links. This might take a few minutes.', 'import-from-copysniper').'</p>';
		
		$fixedlinks = array();
		foreach ($this->filearr as $id => $path) {
			$new_post = array();
			$post = get_post($id);
			$new_post['ID'] = $post->ID;
			$new_post['post_content'] = $this->fix_internal_links($post->post_content, $post->ID);
		
			if (!empty($new_post['post_content']))
				wp_update_post( $new_post );
			$fixedlinks[] .= $post->ID;
		}
		if (!empty($fixedlinks)) {
			echo '<h3>';
			printf(__('All done! <a href="%s">Edit Pages</a>', 'import-from-copysniper'), 'edit.php?post_type='.$posttype);
			echo '</h3>';
		}
		else _e('<p>No links were found.</p>', 'import-from-copysniper');
		//echo '<pre>'.print_r($this->filearr, true).'</pre>';
	}
	
	function print_results($posttype) {
		if(!empty($this->table)) {
			?>
			<table class="widefat page fixed" id="importing" cellspacing="0">
			<thead>
				<tr>
					<th id="id"><?php _e('ID', 'import-from-copysniper'); ?></th>
					<th><?php _e('Title', 'import-from-copysniper'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($this->table as $row) echo $row; ?>
			</tbody>
			</table>
		
			<?php
			echo '<h3>';
			printf(__('Import complete! <a href="%s">Edit Pages</a>', 'import-from-copysniper'), 'edit.php?post_type='.$posttype);
			echo '</h3>';
			flush();
			//echo '<pre>'.print_r($this->filearr, true).'</pre>';
		}
		else {
			echo '<h3>';
			printf(__('Nothing to import :( <a href="%s">Try again?</a>', 'import-from-copysniper'), 'admin.php?import=copysniper');
			echo '</h3>';
			flush();
			exit; // stop if nothing to import
			//echo '<pre>'.print_r($this->filearr, true).'</pre>';
		}
	}
	
	function import() {
		$options = get_option('import_from_copysniper');
		if( !empty( $_POST['import_path'] ) ) {
			
			$this->table = '';
			$this->redirects = '';
			$this->filearr = array();
			$skipdirs = explode(",", $options['skipdirs']);
			$this->skip = array_merge($skipdirs, array( '.', '..', '_vti_cnf', '_notes' ));
			$this->allowed = explode(",", $options['file_extensions']);
			
			//echo '<h3>'.__( 'Importing Copysniper files...', 'import-from-copysniper').'</h3>';
			$this->importPath = stripslashes( $_POST['import_path'] );
			$this->importURL = stripslashes( $_POST['import_url'] );
			$rootdir = stripslashes( $_POST['import_path'] ); // this may not always be correct
			$this->get_files_from_directory($rootdir);
			$this->print_results($options['type']);
			if (isset($options['fix_links']) && $options['fix_links'])
				$this->find_internal_links();
		}
		else {
			_e('<h3>Your import didn\'t work. <a href="admin.php?import=copysniper">Try again</a>?</h3>', 'import_from_copysniper');
		}
		do_action('import_done', 'copysniper');
	}
	
	function dispatch() {
		if (empty ($_GET['step']))
			$step = 0;
		else
			$step = (int) $_GET['step'];

		$this->header();

		switch ($step) {
			case 0 :
				$this->greet();
				break;
			case 1 :
				check_admin_referer('import_from_copysniper'); /* security check */
				$result = $this->import();
				if ( is_wp_error( $result ) )
					echo $result->get_error_message();
				break;
		}

		$this->footer();
	}
	
	function copysniper_importer_styles() {
		?>
		<style type="text/css">
			.wrap h2 {
				padding: 10px 0 10px 0;
			}
		</style>
		<?php
	}

	function Copysniper_Import() {
		add_action('admin_head', array(&$this, 'copysniper_importer_styles'));
	}
	
} // class import_from_copysniper
} // class_exists( 'WP_Importer' )

$import_from_copysniper = new Copysniper_Import();

register_importer('copysniper', __('Copysniper', 'import-from-copysniper'), sprintf(__('Import Copysniper files into WordPress pages.', 'import-from-copysniper') ), array ($import_from_copysniper, 'dispatch'));

// in case this server doesn't have php_mbstring enabled in php.ini...
if (!function_exists('mb_strlen')) {
	function mb_strlen($string) {
		return strlen(utf8_decode($string));
	}
}
if (!function_exists('mb_strrpos')) {
	function mb_strrpos($haystack, $needle, $offset = 0) {
		return strrpos(utf8_decode($haystack), $needle, $offset);
	}
}

?>