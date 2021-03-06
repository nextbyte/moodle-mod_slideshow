<?php  
/// This page prints a particular instance of slideshow
    global $DB;
		global $USER;
    require_once("../../config.php");
    require_once("lib.php");

    $id = optional_param('id',0,PARAM_INT);
    $a = optional_param('a',0,PARAM_INT);
    $autoshow = optional_param('autoshow',0,PARAM_INT);
    $img_num = optional_param('img_num',0,PARAM_INT);
    $recompress = optional_param('recompress',0,PARAM_INT);
    $pause = optional_param('pause',0,PARAM_INT);
		// Value of 0 overrides the last read position in case the first slide is specifically
		// requested (i.e. clicking through the last slide or on the first thumbnail).
		$lr = optional_param('lr', 1, PARAM_INT);

    if ($a) {  // Two ways to specify the module
        $slideshow = $DB->get_record('slideshow', array('id'=>$a), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('slideshow', $slideshow->id, $slideshow->course, false, MUST_EXIST);

    } else {
        $cm = get_coursemodule_from_id('slideshow', $id, 0, false, MUST_EXIST);
        $slideshow = $DB->get_record('slideshow', array('id'=>$cm->instance), '*', MUST_EXIST);
    }

    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

    require_course_login($course, true, $cm);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

		// If a last read record exists it will match these conditions.
		$lastreadconditions = array('userid' => $USER->id, 'slideshowid' => $slideshow->id);

    if ($img_num == 0) {    // qualifies add_to_log, otherwise every slide view increments log
        add_to_log($course->id, "slideshow", "view", "view.php?id=$cm->id", "$slideshow->id");
				
				// If a last read position exists load it.
				if($lr && $DB->record_exists('slideshow_read_positions', $lastreadconditions)) {
					$img_num = $DB->get_record('slideshow_read_positions', $lastreadconditions)->slidenumber;
				} else {
					// User specifically requested first slide, save as last position.
					slideshow_save_last_position($slideshow, $USER, $img_num, $lastreadconditions);
				}
		} else {
			slideshow_save_last_position($slideshow, $USER, $img_num, $lastreadconditions);
		}

	if($_REQUEST['save_position']) die("OK");
	$use_js = $CFG->slideshow_usejavascript;
	/// Print header.
    $PAGE->set_url('/mod/slideshow/view.php',array('id' => $cm->id));
		$PAGE->set_title(get_string('pluginname', 'mod_slideshow') . ': ' . $slideshow->name);
    $PAGE->set_button($OUTPUT->update_module_button($cm->id, 'slideshow'));
    if ($autoshow) { // auto progress of images, no crumb trail
			echo '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title>' . $slideshow->name . '</title>';
			if($use_js) {
				echo '<script type="text/javascript" src="http://yui.yahooapis.com/combo?3.5.1/build/yui/yui.js"></script>
				<script type="text/javascript" src="http://yui.yahooapis.com/combo?2.9.0/build/yahoo/yahoo.js&amp;2.9.0/build/dom/dom.js"></script>';
			}
			echo '</head><body>';
        $slideshow->layout = 9; //layout 9 prevents thumbnails being created
        if (!$pause){
            if(!($autodelay = $slideshow->delaytime)>0) {     // set seconds wait for auto popup progress
                $pause = true;                               // if time 0 then pause...
            } 
        } 
        if ($slideshow->autobgcolor) { // include style to make background black in popup ... 
            echo '<STYLE type="text/css">body {
                background-color:black ! important; background-image : none ! important; color : #ccc ! important} 
                td,h1,h2,h3,h4,h5,h6 { color: #ccc ! important ; } 
                A:link, A:visited{color : #06c}A:hover{color : #0c3}
                </STYLE>';
        }

		
    } else { // normal page header
        echo $OUTPUT->header();
    }
	//comments table style
	echo '
			<style type="text/css">
			.commentButton {
				-moz-box-shadow:inset 0px 0px 0px 0px #ffffff;
				-webkit-box-shadow:inset 0px 0px 0px 0px #ffffff;
				box-shadow:inset 0px 0px 0px 0px #ffffff;
				background-color:#ededed;
				-moz-border-radius:6px;
				-webkit-border-radius:6px;
				border-radius:6px;
				border:1px solid #dcdcdc;
				display:inline-block;
				color:#555;
				font-family:arial;
				font-size:15px;
				font-weight:bold;
				padding:3px 24px;
				text-decoration:none;
				text-shadow:1px 1px 0px #ffffff;
			}.commentButton:hover {
				background-color:#dfdfdf;
			}.commentButton:active {
				position:relative;
				top:1px;
			}
			.commentTable{
				border-radius: 10px 10px 10px 10px;
				box-shadow: 2px 2px 2px #888888;
			}
			/* This imageless css button was generated by CSSButtonGenerator.com */
			
			</style>';

/// Print the main part of the page
    slideshow_secure_script($CFG->slideshow_securepix); // prints javascript ("open image in new window" also conditional on $CFG->slideshow_securepix)
    $conditions = array('contextid'=>$context->id, 'component'=>'mod_slideshow','filearea'=>'content','itemid'=>0);
    $file_records =  $DB->get_records('files', $conditions);
    $fs = get_file_storage();
    $files = array();
    $thumbs = array();
    $resized =  array();
    $showdir = '/';
    foreach ($file_records as $file_record) {
        // check only image files
        if (  preg_match("/\.jpe?g$/i", $file_record->filename) || preg_match("/\.gif$/i", $file_record->filename) || preg_match("/\.png$/i", $file_record->filename)) {
            $showdir = $file_record->filepath;
            if (preg_match("/^thumb_?/i", $file_record->filename)) {
                $filename = str_replace('thumb_','',$file_record->filename);
                $thumbs[$filename] = $filename;
                continue;
            }
            if (preg_match("/^resized_/i", $file_record->filename)) {
                $filename = str_replace('resized_','',$file_record->filename);
                $resized[$filename] = $filename;
                continue;
            }
            $files[$file_record->filename] = new stored_file($fs, $file_record,'content');
        }
    }

    $img_count = 0;
    $maxwidth = $CFG->slideshow_maxwidth;
    $maxheight = $CFG->slideshow_maxheight; 
    $urlroot = $CFG->wwwroot.'/pluginfile.php/'.$context->id.'/mod_slideshow/content/0';
    //$urlroot = $CFG->wwwroot.'/mod/slideshow/pluginfile.php/'.$context->id.'/mod_slideshow/content/0';
    $baseurl = $urlroot.$showdir;
    $filearray = array();
    $error = '';
    foreach ($files as $filename => $file) {
        // OK, let's look at the pictures in the folder ...
        //  iterate and process images 
        if (in_array($filename, $thumbs) || in_array($filename, $resized)) {
            continue; // done those already
        }
        $filearray[$filename] = $filename;
        // create thumbnail if non existant 
        $tfile_record = array('contextid'=>$file->get_contextid(), 'filearea'=>$file->get_filearea(),
            'component'=>$file->get_component(),'itemid'=>$file->get_itemid(), 'filepath'=>$file->get_filepath(),
            'filename'=>'thumb_'.$file->get_filename(), 'userid'=>$file->get_userid());
        try {
            // this may fail for various reasons
            $fs->convert_image($tfile_record, $file, 80, 60, true);
        } catch (Exception $e) {
            //oops!
            $img_count=0;
            $error = '<p><b>'.$e->getMessage().'</b> '.$filename.'</p>';
            break;
        }
        // create resized image if non existant 
        $tfile_record = array('contextid'=>$file->get_contextid(), 'filearea'=>$file->get_filearea(),
            'component'=>$file->get_component(),'itemid'=>$file->get_itemid(), 'filepath'=>$file->get_filepath(),
            'filename'=>'resized_'.$file->get_filename(), 'userid'=>$file->get_userid());
        try {
            // this may fail for various reasons
            $fs->convert_image($tfile_record, $file, $maxwidth, $maxheight, true);
        } catch (Exception $e) {
            //oops!
            $img_count=0;
            $error = '<p><b>'.$e->getMessage().'</b> '.$filename.'</p>';
            break;
        }
        if(!$slideshow->keeporiginals) {
            $file->delete(); // dump the original
        }
        $img_count ++;
    }
    if ($img_count == 0 and count($resized) > 0) {
        $filearray = $resized;
        $img_count = count($filearray);
    } elseif ($img_count < count($resized)) {
        $filearray = array_merge($filearray,$resized);
        $img_count = count($filearray);
    }
        
    sort($filearray);

	
	$container_width = $CFG->slideshow_maxwidth;
	// Add space for second column if comments are allowed or if captions are displayed to the right.
	if($slideshow->commentsallowed || $slideshow->filename == 3) {
		$container_width += 320;
	}
	$margin_string = "margin: " . ($CFG->slideshow_maxheight + 20) . "px 0 0 0;";
	// If captions are displayed below image the margin has already been taken care by the caption paragraph.
	if($slideshow->filename == 2) {
		$margin_string = "margin: 0 0 0 0;";
	}

?>
	<style>
		#slide {
			width: <?php echo $CFG->slideshow_maxwidth; ?>; 
			height: <?php echo $CFG->slideshow_maxheight; ?>;
		}
		#slide a img {
			margin-left: -15px;
		}
		#slideshowmain {
			width: <?php echo $container_width; ?>px;
			margin: 0px auto;
			height: 300px;
		}
		#slideshowcenter {
			width: <?php echo $CFG->slideshow_maxwidth; ?>px;
			float: left;
		}
		#previous {
			width: <?php echo $CFG->slideshow_maxwidth/2; ?>px;
			position: absolute; 
			z-index: 11; 
			vertical-align:middle; 
			color:white; 
			display:block;
			float:right;
			margin-left:0px; 
			height: <?php echo $CFG->slideshow_maxheight ?>px;
			/*height: 30%;*/
		}
		#slideshowul {
			text-align:left; 
			list-style: none;
			<? echo $margin_string; ?> 
			width: 100%;		
		}
</style>

<?php
	if($use_js) 
	{
		echo '<img id="preloadimg" style="display:none" src="'.$baseurl.'resized_'.$filearray[$img_num+1].'">';
		$img_array = '"'.$baseurl.'resized_'.implode('",'."\n".'"'.$baseurl.'resized_',$filearray).'"';
?>
<script type="text/javascript">
	var images = new Array(<?php echo $img_array ?>);
	var current_slide = <?php echo $img_num; ?>;
	var total_slides = <?php echo $img_count; ?>;
<?php if($autoshow) { ?>	
	var myInterval = setTimeout(next_slide, <?php echo $autodelay; ?>000);
<?php } ?>	
	function next_slide()
	{
		if(current_slide<total_slides)
		{
<?php if($autoshow) { ?>	
			clearTimeout(myInterval);
<?php } ?>	
			YUI().use('node-load', function (Y) {
				Y.one("#slideimg").set('src',images[++current_slide]);
				Y.one("#preloadimg").set('src',images[current_slide+1]);
				Y.io('http://cursuri.nextbyte.ro/mod/slideshow/view.php', {
				    method: 'GET',
				    data: 'id=<?php echo $cm->id; ?>&img_num='+current_slide+'&lr=0&save_position=1',
				});
			});
<?php if($autoshow) { ?>	
			myInterval = setTimeout(next_slide, <?php echo $autodelay; ?>000);
<?php } ?>	
		}
	}
	
	function prev_slide()
	{
		if(current_slide>0)
		{
<?php if($autoshow) { ?>	
			clearTimeout(myInterval);
<?php } ?>	
			YUI().use('node-load', function (Y) {
				current_slide--;
				Y.one("#slideimg").set('src',images[current_slide]);
				Y.io('http://cursuri.nextbyte.ro/mod/slideshow/view.php', {
				    method: 'GET',
				    data: 'id=<?php echo $cm->id; ?>&img_num='+current_slide+'&lr=0&save_position=1',
				});
			});
<?php if($autoshow) { ?>	
			myInterval = setTimeout(next_slide, <?php echo $autodelay; ?>000);
<?php } ?>	
		}
	}
</script>
<?php
	}
	if (!$autoshow && $CFG->slideshow_scaleonsmallscreen){
?>
	<style>
		@media all and (min-width: 481px) and (max-width: 1024px) {
			#slide a img {
				margin-left: -40px;
			}
			#slideshowul {
				margin: 370px 0 0 0;
			}
			#slideimg {
				width: 480px;
			}
			#previous {
				width: 240px;
				height: 360px;
			}
			#previous img {
				width: 240px;
				height: 360px;
			}
			#slide {
				width: 480px;
				height: 360px;
			}
			#slideshowmain {
				width: 480px;
			}
			#slideshowcenter {
				width: 480px;
				height: 360px;
			}
		}
		
		@media all and (max-width: 480px) {
			#slideshowul {
				margin: 250px 0 0 0;
			}
			#slideimg {
				width: 320px;
			}
			#previous {
				width: 160px;
				height: 240px;
			}
			#previous img {
				width: 160px;
				height: 240px;
			}
			#slide {
				width: 320px;
				height: 240px;
			}
			#slideshowmain {
				width: 320px;
			}
			#slideshowcenter {
				width: 320px;
				height: 240px;
			}
		}
		@media all and (max-height: 360px) {
			#slideshowul {
				margin: 250px 0 0 0;
			}
			#slideimg {
				width: 320px;
			}
			#previous {
				width: 160px;
				height: 240px;
			}
			#previous img {
				width: 160px;
				height: 240px;
			}
			#slide {
				width: 320px;
				height: 240px;
			}
			#slideshowmain {
				width: 320px;
			}
			#slideshowcenter {
				width: 320px;
				height: 240px;
			}
		}
	</style>
<?
	}
    if ($slideshow->centred){
		$container_width = $CFG->slideshow_maxwidth;
		// Add space for second column if comments are allowed or if captions are displayed to the right.
		if($slideshow->commentsallowed || $slideshow->filename == 3) {
			$container_width += 320;
		}
		echo '<div id="slideshowmain">';
	}
		
    if($img_count) {
		
	    echo "<div id=\"previous\">";
		if($img_num>0) {
			if($use_js) {
	        	echo '<a name="pic" href="#" onclick="prev_slide();">';
			}
			else
			{
		        echo '<a href="?id='.($cm->id).'&img_num='.fmod($img_num-1,$img_count).'&autoshow='.$autoshow.'&lr=0">';
			}
	        echo '<img src="/mod/slideshow/pix/previous.png" style="z-index: 1">';
	        echo "</a></div>";
			echo '<div id="slideshowcenter">';
		}
        //
        // $slideshow->layout defines thumbnail position - 1 is on top, 2 is bottom
        // $slideshow->filename defines the position of captions. 1 is on top, 2 is bottom, 3 is on the right
        //
        if ($slideshow->layout == 1){
            // print thumbnail row
            slideshow_display_thumbs($filearray);
        }
        // process caption text
        $currentimage = slideshow_filetidy($filearray[$img_num]);
        $caption_array[$currentimage] = slideshow_caption_array($slideshow->id,$currentimage);
            
        if (isset($caption_array[$currentimage])){
            $captionstring =  $caption_array[$currentimage]['caption'];
            $titlestring = $caption_array[$currentimage]['title'];
        } else {
            $captionstring = $currentimage;
            $titlestring='';
        }
        //
        // if there is a title, show it!
        if($titlestring){
            echo format_text('<h1>'.$titlestring.'</h1>');
        }
        if ($slideshow->filename == 1){
            echo '<p>'.$captionstring.'<p>';
				}

        // display main picture, with link to next page and plain text for alt and title tags
				echo "<div id=\"slide\" style=\"position: absolute; z-index: 10; \">";
				// The lr parameter overrides the last read position, in case the user reaches the end of the slideshow and wants to see the first slide.
		if($use_js) {
        	echo '<a name="pic" href="#" onclick="next_slide();">';
		}
		else {
			echo '<a name="pic" href="?id='.($cm->id).'&img_num='.fmod($img_num+1,$img_count).'&autoshow='.$autoshow.'&lr=0">';
		}
        echo '<img id="slideimg" src="'.$baseurl.'resized_'.$filearray[$img_num].'" alt="'.$filearray[$img_num]
            .'" title="'.$filearray[$img_num].'" style="z-index: 1">';
        echo "</a></div>";
 
				// If there is media on this slide overlay it over the slide.
				if($media = slideshow_slide_get_media($slideshow->id, $img_num)) {
					$top = $media->y;
					$left = $media->x;
					echo '<div id="media_wrapper" style="position: absolute; z-index: 1000">';
					echo '<div id="media" style="position: relative; top: ' . $top . 'px; left: ' . $left . 'px">';
					echo $PAGE->get_renderer('core', 'media')->embed_url(new moodle_url($media->url), '', $media->width, $media->height); // Empty string is because 2nd param is title attr.
					echo '</div>';
					echo '</div>';
				}

       if ($slideshow->filename == 2){
						$margin_top = $CFG->slideshow_maxheight + 20;
            echo '<p style="margin: ' . $margin_top . 'px 0 0 0;">'.$captionstring.'</p>';
        }
            
        if ($slideshow->layout == 2){
            // print thumbnail row
			echo "<div id='thumbnail_container' style='float: left; margin-top: ".($CFG->slideshow_maxheight + 10)."px;'>";
            slideshow_display_thumbs($filearray);
			echo "</div>";
        }
          
        if (!$autoshow){
            // set up regular navigation options (autopoup, image in new window, teacher options)
            $popheight = $CFG->slideshow_maxheight +100;
            $popwidth = $CFG->slideshow_maxwidth +100;
						// Set a fixed top margin of maxheight+20 because media wrapper is absolute, need to display navigation options underneath it.
						$margin_string = "margin: " . ($CFG->slideshow_maxheight + 20) . "px 0 0 0;";
						// If captions are displayed below image the margin has already been taken care by the caption paragraph.
						if($slideshow->filename == 2) {
							$margin_string = "margin: 0 0 0 0;";
						}
            echo '<ul id="slideshowul"><li><a target="popup" href="?id='
                .($cm->id)."&autoshow=1\" onclick=\"return openpopup('/mod/slideshow/view.php?id="
                .($cm->id)."&autoshow=1', 'popup', 'menubar=0,location=0,scrollbars,resizable,width=$popwidth,height=$popheight', 0);\">"
                .get_string('autopopup','slideshow')."</a></li>";
            if(! $CFG->slideshow_securepix){
                if (isset($slideshow->keeporiginals) and 
                    $DB->record_exists('files', array('contextid'=>$context->id, 'filepath'=>$showdir, 'filename' => $filearray[$img_num]))) {
                    echo '<li><a href="'.$baseurl.$filearray[$img_num].'" target="_blank">'.get_string('open_new', 'slideshow').'</a></li>';
                } else {
                    echo '<li><a href="'.$baseurl.'resized_'.$filearray[$img_num].'" target="_blank">'.get_string('open_new', 'slideshow').'</a></li>';
                }
            }
            if (has_capability('moodle/course:update',$context)){
                echo '<li><a href="captions.php?id='.$cm->id.'">'.get_string('edit_captions', 'slideshow').'</a></li>';
								echo '<li><a href="media.php?id=' . $cm->id . '&img_num=' . $img_num . '">' . get_string('media_add', 'slideshow') . '</a></li>';
            }
						echo '</ul>';
        } else {
            //
            // set up autoplay navigation (< || >)
            echo '<p align="center"><a href="?id='.($cm->id).'&img_num='.fmod($img_count+$img_num-1,$img_count).'&autoshow='.$autoshow."\">&lt;&lt;</a>";
            if (!$pause){
                echo '<a href="?id='.($cm->id).'&img_num='.$img_num.'&autoshow='.$autoshow."&pause=1\">||</a>";
				if(!$use_js)
				{
	                echo '<meta http-equiv="Refresh" content="'.$autodelay.'0000; url=?id='
	                    .($cm->id).'&img_num='.fmod($img_num+1,$img_count)."&autoshow=1\">";
				}
            } else {
                echo "||";
            }
            echo '<a href="?id='.($cm->id).'&img_num='.fmod($img_num+1,$img_count).'&autoshow='.$autoshow."\">&gt;&gt;</a></p>";
        }

				// Close slide column.
				echo'</div>';
				if($slideshow->centred) {
					echo '</div>';
				}

				// Second column only displayed if comments are allowed or captions are shown to the right.
				if($slideshow->commentsallowed || $slideshow->filename==3) {
					// Fixed width because, depending on user's resolution, maxwidth could cause the second column to be very narrow.
					// In that case it makes more sense for the column to be cleared under the image.					
					echo '<div class="commentTable" style="float: left; width: 350px; margin: 70px 0 0 20px; background-color:#336699; color:#F9F6F4; font-family: Lucida Sans Unicode, Lucida Grande, sans-serif;">';
					if($slideshow->filename == 3) {
						echo '<p>' . $captionstring . '</p>';
					}

					if($slideshow->commentsallowed) {
						echo '<center>';
						echo '<h3>' . get_string('comments_header', 'slideshow') . '</h3>';
						echo '</center>';
						//echo '<ul style="list-style: none;">';
						$comments = array();
						$comments = slideshow_slide_comments_array($slideshow->id, $img_num);

						if($comments){
							$commentcolor = FALSE;
							foreach($comments as $comment) : 
								$user = $DB->get_record('user', array('id' => $comment->userid)); 
										if($commentcolor === FALSE){
											echo '<div style="background-color:#CCCCCC; color: #222; padding-top: 1px; padding-bottom: 1px;">';
												echo '<p style="padding-left: 3px; font-style:italic;">';
													echo $user->firstname . ' ' . $user->lastname;
												echo '</p>';
												echo '<div style="padding-left: 15px;width: 330px"><p>';
													echo $comment->slidecomment;
												echo '</p></div>';
												$commentcolor = TRUE;
										}
										else {
											echo '<div style="background-color:#F9F6F4; color: #222; padding-top: 1px; padding-bottom: 1px;">';
												echo '<p style="padding-left: 3px; font-style:italic;">';
													echo $user->firstname . ' ' . $user->lastname;
												echo '</p>';
												echo '<div style="padding-left: 15px; width: 330px">';
													echo $comment->slidecomment;
												echo '</div>';
												$commentcolor = FALSE;
										}
											echo '</div>'	
						?>	
										
									
								
<?php				endforeach;
						}

						//echo '</ul>';
						echo '<center><br><a href="comments.php?id=' . $cm->id . '&img_num=' . $img_num . '" class="commentButton">' . get_string('comment_add', 'slideshow') . '</a></center>';
						echo '<br></div>';						}
				}

    } else {
        echo '<p>'.get_string('none_found', 'slideshow').' <b>'.$showdir.'</b></p>';
        echo '<p><b>'.$error.'</b></p>';
				
				// Close slide column.
				echo'</div>';
				if($slideshow->centred) {
					echo '</div>';
				}
    }

/// Finish the page
    if ($autoshow){
        echo '</body></html>';
    } else {
    echo $OUTPUT->footer($course);
    }
?>
