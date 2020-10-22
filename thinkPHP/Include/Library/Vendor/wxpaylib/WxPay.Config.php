<?php
/**
* 	配置账号信息
*/
// define('WxPayConfig_APPID', 'wx94f43e78428c93bd');
// define('WxPayConfig_MCHID', '1288862801');
// define('WxPayConfig_KEY', '4k7k0h575fddsq56aojhqad4alveb933');
// define('WxPayConfig_APPSECRET', '558c136097e498e251e167bc91f402c5');
// define('WxPayConfig_SSLCERT_PATH', '/ylxcert/apiclient_cert.pem');
// define('WxPayConfig_SSLKEY_PATH', '/ylxcert/apiclient_key.pem');


define('WxPayConfig_CURL_PROXY_HOST', '0.0.0.0');
define('WxPayConfig_CURL_PROXY_PORT', 0);
define('WxPayConfig_REPORT_LEVENL', 1);
class WxPayConfig
{
// 	//=======【基本信息设置】=====================================
// 	//
// 	/**
// 	 * TODO: 修改这里配置为您自己申请的商户信息
// 	 * 微信公众号信息配置
// 	 * 
// 	 * APPID：绑定支付的APPID（必须配置，开户邮件中可查看）
// 	 * 
// 	 * MCHID：商户号（必须配置，开户邮件中可查看）
// 	 * 
// 	 * KEY：商户支付密钥，参考开户邮件设置（必须配置，登录商户平台自行设置）
// 	 * 设置地址：https://pay.weixin.qq.com/index.php/account/api_cert
// 	 * 
// 	 * APPSECRET：公众帐号secert（仅JSAPI支付的时候需要配置， 登录公众平台，进入开发者中心可设置），
// 	 * 获取地址：https://mp.weixin.qq.com/advanced/advanced?action=dev&t=advanced/dev&token=2005451881&lang=zh_CN
// 	 * @var string
// 	 */
// 	// const APPID = 'wx92fcf9b2f7b123d1';
// 	// const MCHID = '1250088001';
// 	// const KEY = 'abc1250088001defabc1250088001def';
// 	// const APPSECRET = '3eaed6ef4948daeb7f3ceb422d552501';


// // 易分销营销平台
// // hsq@vjudian.com  123456
// // 1288862801@1288862801   505257
// 	const APPID = 'wx94f43e78428c93bd';
// 	const MCHID = '1288862801';
// 	const KEY = '4k7k0h575fddsq56aojhqad4alveb933';
// 	const APPSECRET = '558c136097e498e251e167bc91f402c5';
// 	//=======【证书路径设置】=====================================
// 	/**
// 	 * TODO：设置商户证书路径
// 	 * 证书路径,注意应该填写绝对路径（仅退款、撤销订单时需要，可登录商户平台下载，
// 	 * API证书下载地址：https://pay.weixin.qq.com/index.php/account/api_cert，下载之前需要安装商户操作证书）
// 	 * @var path
// 	 */
// 	const SSLCERT_PATH = '/ylxcert/apiclient_cert.pem';
// 	const SSLKEY_PATH = '/ylxwxpcert/apiclient_key.pem';
	
// 	//=======【curl代理设置】===================================
// 	/**
// 	 * TODO：这里设置代理机器，只有需要代理的时候才设置，不需要代理，请设置为0.0.0.0和0
// 	 * 本例程通过curl使用HTTP POST方法，此处可修改代理服务器，
// 	 * 默认CURL_PROXY_HOST=0.0.0.0和CURL_PROXY_PORT=0，此时不开启代理（如有需要才设置）
// 	 * @var unknown_type
// 	 */
// 	const CURL_PROXY_HOST = "0.0.0.0";//"10.152.18.220";
// 	const CURL_PROXY_PORT = 0;//8080;
	
// 	//=======【上报信息配置】===================================
// 	/**
// 	 * TODO：接口调用上报等级，默认紧错误上报（注意：上报超时间为【1s】，上报无论成败【永不抛出异常】，
// 	 * 不会影响接口调用流程），开启上报之后，方便微信监控请求调用的质量，建议至少
// 	 * 开启错误上报。
// 	 * 上报等级，0.关闭上报; 1.仅错误出错上报; 2.全量上报
// 	 * @var int
// 	 */
// 	const REPORT_LEVENL = 1;
}
