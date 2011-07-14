<?php
    function opensearch_content(&$a) {
    	
		$tpl = get_markup_template('opensearch.tpl');
	
		header("Content-type: application/opensearchdescription+xml");
	
		$o = replace_macros($tpl, array(
			'$baseurl' => z_path(),
			'$nodename' => $a->get_hostname(),
		));
		
		echo $o;
		
		killme();
		
	}
?>