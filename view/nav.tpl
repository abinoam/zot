$langselector

<div id="site-location">$sitelocation</div>

{{ if $nav.logout }}<a id="nav-logout-link" class="nav-link $nav.logout.2" href="$nav.logout.0">$nav.logout.1</a> {{ endif }}
{{ if $nav.login }}<a id="nav-login-link" class="nav-login-link $nav.login.2" href="$nav.login.0">$nav.login.1</a> {{ endif }}

<span id="nav-link-wrapper" >

{{ if $nav.register }}<a id="nav-register-link" class="nav-commlink $nav.register.2" href="$nav.register.0">$nav.register.1</a>{{ endif }}
	
<a id="nav-help-link" class="nav-link $nav.help.2" target="friendika-help" href="$nav.help.0">$nav.help.1</a>
	
{{ if $nav.apps }}<a id="nav-apps-link" class="nav-link $nav.apps.2" href="$nav.apps.0">$nav.apps.1</a>{{ endif }}

<a id="nav-search-link" class="nav-link $nav.search.2" href="$nav.search.0">$nav.search.1</a>
<a id="nav-directory-link" class="nav-link $nav.directory.2" href="$nav.directory.0">$nav.directory.1</a>

{{ if $nav.admin }}<a id="nav-admin-link" class="nav-link $nav.admin.2" href="$nav.admin.0">$nav.admin.1</a>{{ endif }}

{{ if $nav.network }}
<a id="nav-network-link" class="nav-commlink $nav.network.2" href="$nav.network.0">$nav.network.1</a>
<span id="net-update" class="nav-ajax-left"></span>
{{ endif }}
{{ if $nav.home }}
<a id="nav-home-link" class="nav-commlink $nav.home.2" href="$nav.home.0">$nav.home.1</a>
<span id="home-update" class="nav-ajax-left"></span>
{{ endif }}
{{ if $nav.community }}
<a id="nav-community-link" class="nav-commlink $nav.community.2" href="$nav.community.0">$nav.community.1</a>
{{ endif }}
{{ if $nav.notifications }}
<a id="nav-notify-link" class="nav-commlink $nav.notifications.2" href="$nav.notifications.0">$nav.notifications.1</a>
<span id="notify-update" class="nav-ajax-left"></span>
{{ endif }}
{{ if $nav.messages }}
<a id="nav-messages-link" class="nav-commlink $nav.messages.2" href="$nav.messages.0">$nav.messages.1</a>
<span id="mail-update" class="nav-ajax-left"></span>
{{ endif }}

{{ if $nav.manage }}<a id="nav-manage-link" class="nav-commlink $nav.manage.2" href="$nav.manage.0">$nav.manage.1</a>{{ endif }}

{{ if $nav.settings }}<a id="nav-settings-link" class="nav-link $nav.settings.2" href="$nav.settings.0">$nav.settings.1</a>{{ endif }}
{{ if $nav.profiles }}<a id="nav-profiles-link" class="nav-link $nav.profiles.2" href="$nav.profiles.0">$nav.profiles.1</a>{{ endif }}

{{ if $nav.contacts }}<a id="nav-contacts-link" class="nav-link $nav.contacts.2" href="$nav.contacts.0">$nav.contacts.1</a>{{ endif }}
</span>
<span id="nav-end"></span>
<span id="banner">$banner</span>
