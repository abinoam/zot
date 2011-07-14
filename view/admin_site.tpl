<div id='adminpage'>
	<h1>$title - $page</h1>
	
	<form action="$baseurl/admin/site" method="post">
	
	{{ inc field_input.tpl with $field=$sitename }}{{ endinc }}
	{{ inc field_textarea.tpl with $field=$banner }}{{ endinc }}
	{{ inc field_select.tpl with $field=$language }}{{ endinc }}
	{{ inc field_select.tpl with $field=$theme }}{{ endinc }}
	
	<div class="submit"><input type="submit" name="page_site" value="$submit" /></div>
	
	<h3>$registration</h3>
	{{ inc field_input.tpl with $field=$register_text }}{{ endinc }}
	{{ inc field_select.tpl with $field=$register_policy }}{{ endinc }}
	
	{{ inc field_checkbox.tpl with $field=$no_multi_reg }}{{ endinc }}
	{{ inc field_checkbox.tpl with $field=$no_openid }}{{ endinc }}
	{{ inc field_checkbox.tpl with $field=$no_gravatar }}{{ endinc }}
	{{ inc field_checkbox.tpl with $field=$no_regfullname }}{{ endinc }}
	
	<div class="submit"><input type="submit" name="page_site" value="$submit" /></div>

	<h3>$upload</h3>
	{{ inc field_input.tpl with $field=$maximagesize }}{{ endinc }}
	
	<h3>$corporate</h3>
	{{ inc field_input.tpl with $field=$allowed_sites }}{{ endinc }}
	{{ inc field_input.tpl with $field=$allowed_email }}{{ endinc }}
	{{ inc field_checkbox.tpl with $field=$block_public }}{{ endinc }}
	{{ inc field_checkbox.tpl with $field=$force_publish }}{{ endinc }}
	{{ inc field_checkbox.tpl with $field=$no_community_page }}{{ endinc }}
	{{ inc field_checkbox.tpl with $field=$ostatus_disabled }}{{ endinc }}
	{{ inc field_checkbox.tpl with $field=$dfrn_only }}{{ endinc }}
	{{ inc field_input.tpl with $field=$global_directory }}{{ endinc }}
	
	<div class="submit"><input type="submit" name="page_site" value="$submit" /></div>
	
	<h3>$advanced</h3>
	{{ inc field_checkbox.tpl with $field=$no_utf }}{{ endinc }}
	{{ inc field_checkbox.tpl with $field=$verifyssl }}{{ endinc }}
	{{ inc field_input.tpl with $field=$proxy }}{{ endinc }}
	{{ inc field_input.tpl with $field=$proxyuser }}{{ endinc }}
	{{ inc field_input.tpl with $field=$timeout }}{{ endinc }}
	
	<div class="submit"><input type="submit" name="page_site" value="$submit" /></div>
	
	</form>
</div>
