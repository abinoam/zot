	<entry>
		<author>
			<name>$name</name>
			<uri>$profile_page</uri>
			<link rel="photo"  type="image/jpeg" media:width="80" media:height="80" href="$thumb" />
			<link rel="avatar" type="image/jpeg" media:width="80" media:height="80" href="$thumb" />
		</author>

		<id>$item_id</id>
		<title>$title</title>
		<published>$published</published>
		<content type="$type" >$content</content>
		<link rel="mentioned" href="$accturi" />
		<as:actor>
		<as:object_type>http://activitystrea.ms/schema/1.0/person</as:object_type>
		<id>$profile_page</id>
		<title></title>
 		<link rel="avatar" type="image/jpeg" media:width="175" media:height="175" href="$photo"/>
		<link rel="avatar" type="image/jpeg" media:width="80" media:height="80" href="$thumb"/>
		<poco:preferredUsername>$nick</poco:preferredUsername>
		<poco:displayName>$name</poco:displayName>
		</as:actor>
 		<as:verb>$verb</as:verb>
		<as:object>
		<as:object_type></as:object_type>
		</as:object>
		<as:target>
		<as:object_type></as:object_type>
		</as:target>
	</entry>
