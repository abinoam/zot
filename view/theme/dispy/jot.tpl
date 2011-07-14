
<div id="profile-jot-wrapper" > 
	<div id="profile-jot-banner-wrapper">
		<div id="profile-jot-desc" >&nbsp;</div>
		<div id="character-counter" class="grey">0</div>
		<div id="profile-rotator-wrapper" style="display: $visitor;" >
			<img id="profile-rotator" src="images/rotator.gif" alt="$wait" title="$wait" style="display:none;"  />
		</div> 		
	</div>

	<form id="profile-jot-form" action="$action" method="post" >
		<input type="hidden" name="type" value="wall" />
		<input type="hidden" name="profile_uid" value="$profile_uid" />
		<input type="hidden" name="return" value="$return_path" />
		<input type="hidden" name="location" id="jot-location" value="$defloc" />
		<input type="hidden" name="coord" id="jot-coord" value="" />
		<input type="hidden" name="title" id="jot-title" value="" />
		<input type="hidden" name="post_id" value="$post_id" />

		<textarea rows="5" style="width:100%" class="profile-jot-text" id="profile-jot-text" name="body" >$content</textarea>


<div id="profile-jot-submit-wrapper" >
	<div id="profile-jot-perms" class="profile-jot-perms" style="display: $visitor;" ><a id="jot-perms-icon" class="icon $lockstate"  title="$permset" onClick="openClose('profile-jot-acl-wrapper'); openClose('profile-jot-email-wrapper'); openClose('profile-jot-networks');return false;"></a>$bang</div>
	<input type="submit" id="profile-jot-submit" name="submit" value="$share" />
</div>

	<div id="profile-upload-wrapper" class="jot-tool" style="display: $visitor;" >
		<div id="wall-image-upload-div" ><a onclick="return false;" id="wall-image-upload" class="icon border camera" title="$upload"></a></div>
	</div>
	<div id="profile-attach-wrapper" class="jot-tool" style="display: $visitor;" >
		<div id="wall-file-upload-div" ><a href="#" onclick="return false;" id="wall-file-upload" class="icon border attach" title="$attach"></a></div>
	</div>  
	<div id="profile-link-wrapper" class="jot-tool" style="display: $visitor;" ondragenter="linkdropper(event);" ondragover="linkdropper(event);" ondrop="linkdrop(event);" >
		<a id="profile-link" class="icon border  link" title="$weblink" ondragenter="return linkdropper(event);" ondragover="return linkdropper(event);" ondrop="linkdrop(event);" onclick="jotGetLink(); return false;"></a>
	</div> 
	<div id="profile-youtube-wrapper" class="jot-tool" style="display: $visitor;" >
		<a id="profile-youtube" class="icon border  youtube" title="$youtube" onclick="jotGetVideo(); return false;"></a>
	</div> 
	<div id="profile-video-wrapper" class="jot-tool" style="display: $visitor;" >
		<a id="profile-video" class="icon border  video" title="$video" onclick="jotVideoURL(); return false;"></a>
	</div> 
	<div id="profile-audio-wrapper" class="jot-tool" style="display: $visitor;" >
		<a id="profile-audio" class="icon border  audio" title="$audio" onclick="jotAudioURL(); return false;"></a>
	</div> 
	<div id="profile-location-wrapper" class="jot-tool" style="display: $visitor;" >
		<a id="profile-location" class="icon border  globe" title="$setloc" onclick="jotGetLocation(); return false;"></a>
	</div> 
	<div id="profile-nolocation-wrapper" class="jot-tool" style="display: none;" >
		<a id="profile-nolocation" class="icon border  noglobe" title="$noloc" onclick="jotClearLocation(); return false;"></a>
	</div> 
	<div id="profile-title-wrapper" class="jot-tool" style="display: $visitor;" >
		<a id="profile-title" class="icon border  article" title="$title" onclick="jotTitle(); return false;"></a>
	</div> 

	<div id="profile-jot-plugin-wrapper">
  	$jotplugins
	</div>

	<div id="profile-jot-tools-end"></div>
	
	<div id="profile-jot-email-wrapper" style="display: none;" >
	<div id="profile-jot-email-label">$emailcc</div><input type="text" name="emailcc" id="profile-jot-email" title="$emtitle">
	<div id="profile-jot-email-end"></div>
	</div>
	<div id="profile-jot-networks" style="display: none;" >
	$jotnets
	</div>
	<div id="profile-jot-networks-end"></div>
	<div id="profile-jot-acl-wrapper" style="display: none;" >$acl</div>


<div id="profile-jot-end"></div>
</form>
</div>
