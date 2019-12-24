/* To avoid CSS expressions while still supporting IE 7 and IE 6, use this script */
/* The script tag referencing this file must be placed before the ending body tag. */

/* Use conditional comments in order to target IE 7 and older:
	<!--[if lt IE 8]><!-->
	<script src="ie7/ie7.js"></script>
	<!--<![endif]-->
*/

(function() {
	function addIcon(el, entity) {
		var html = el.innerHTML;
		el.innerHTML = '<span style="font-family: \'feicon\'">' + entity + '</span>' + html;
	}
	var icons = {
		'feicon-pwd': '&#xe900;',
		'feicon-user': '&#xe901;',
		'feicon-account': '&#xe902;',
		'feicon-company': '&#xe903;',
		'feicon-phone': '&#xe904;',
		'feicon-img-code': '&#xe905;',
		'feicon-img-pic': '&#xe906;',
		'feicon-file-pic': '&#xe907;',
		'feicon-mark': '&#xe909;',
		'feicon-msg': '&#xe90a;',
		'feicon-wechat': '&#xe90e;',
		'feicon-customer-service': '&#xe90f;',
		'feicon-notice': '&#xe910;',
		'0': 0
		},
		els = document.getElementsByTagName('*'),
		i, c, el;
	for (i = 0; ; i += 1) {
		el = els[i];
		if(!el) {
			break;
		}
		c = el.className;
		c = c.match(/feicon-[^\s'"]+/);
		if (c && icons[c[0]]) {
			addIcon(el, icons[c[0]]);
		}
	}
}());
