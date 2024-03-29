<?php
/*
Plugin Name: Erima Zarinpal Donate - حمایت مالی 
Plugin URI: http://dblog.ir/?page_id=47
Description: افزونه حمایت مالی از وبسایت ها -- برای استفاده تنها کافی است کد زیر را درون بخشی از برگه یا نوشته خود قرار دهید  [ErimaZarinpalDonate]
Version: 1.0
Author:  سید امیر
Author URI: http://dblog.ir
*/

defined('ABSPATH') or die('Access denied!');
define ('ErimaZarinpalDonateDIR', plugin_dir_path( __FILE__ ));
define ('LIBDIR'  , ErimaZarinpalDonateDIR.'/lib');
define ('TABLE_DONATE'  , 'erima_donate');

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

if ( is_admin() )
{
        add_action('admin_menu', 'EZD_AdminMenuItem');
        function EZD_AdminMenuItem()
        {
				add_menu_page( 'تنظیمات افزونه حمایت مالی - زرین پال', 'حمات مالی', 'administrator', 'EZD_MenuItem', 'EZD_MainPageHTML', /*plugins_url( 'myplugin/images/icon.png' )*/'', 6 ); 
        add_submenu_page('EZD_MenuItem','نمایش حامیان مالی','نمایش حامیان مالی', 'administrator','EZD_Hamian','EZD_HamianHTML');
        }
}

function EZD_MainPageHTML()
{
	include('EZD_AdminPage.php');
}

function EZD_HamianHTML()
{
	include('EZD_Hamian.php');
}


add_action( 'init', 'ErimaZarinpalDonateShortcode');
function ErimaZarinpalDonateShortcode(){
	add_shortcode('ErimaZarinpalDonate', 'ErimaZarinpalDonateForm');
}

function ErimaZarinpalDonateForm() {
  $out = '';
  $error = '';
  $message = '';
  
	$MerchantID = get_option( 'EZD_MerchantID');
  $EZD_IsOK = get_option( 'EZD_IsOK');
  $EZD_IsError = get_option( 'EZD_IsError');
  $EZD_Unit = get_option( 'EZD_Unit');
  
  $Amount = '';
  $Description = '';
  $Name = '';
  $Mobile = '';
  $Email = '';
  
  //////////////////////////////////////////////////////////
  //            REQUEST
  if(isset($_POST['submit']) && $_POST['submit'] == 'پرداخت')
  {

    
    if($MerchantID == '')
    {
      $error = 'کد دروازه پرداخت وارد نشده است' . "<br>\r\n";
    }
    
    
    $Amount = filter_input(INPUT_POST, 'EZD_Amount', FILTER_SANITIZE_SPECIAL_CHARS);
    
    if(is_numeric($Amount) != false)
    {
      //Amount will be based on Toman  - Required
      if($EZD_Unit == 'ریال')
        $SendAmount =  $Amount / 10;
      else
        $SendAmount =  $Amount;
    }
    else
    {
      $error .= 'مبلغ به درستی وارد نشده است' . "<br>\r\n";
    }
    
    $Description =    filter_input(INPUT_POST, 'EZD_Description', FILTER_SANITIZE_SPECIAL_CHARS);  // Required
    $Name =           filter_input(INPUT_POST, 'EZD_Name', FILTER_SANITIZE_SPECIAL_CHARS);  // Required
    $Mobile =         filter_input(INPUT_POST, 'mobile', FILTER_SANITIZE_SPECIAL_CHARS); // Optional
    $Email =          filter_input(INPUT_POST, 'email', FILTER_SANITIZE_SPECIAL_CHARS); // Optional
    
    $SendDescription = $Name . ' | ' . $Mobile . ' | ' . $Email . ' | ' . $Description ;  
    
    if($error == '') // اگر خطایی نباشد
    {
      $CallbackURL = EZD_GetCallBackURL();  // Required


        $data = array('MerchantID' => $MerchantID,
            'Email' 		=> $Email,
            'Mobile' 		=> $Mobile,
            'Amount' => $SendAmount,
            'CallbackURL' => $CallbackURL,
            'Description' => $SendDescription);
        $jsonData = json_encode($data);
        $ch = curl_init('https://www.zarinpal.com/pg/rest/WebGate/PaymentRequest.json');
        curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ));
        $result = curl_exec($ch);
        $err = curl_error($ch);
        $result = json_decode($result, true);
        curl_close($ch);
      //Redirect to URL You can do it also by creating a form
                if ($result["Status"] == 100) {
        // WruteToDB
        
        EZD_AddDonate(array(
					'Authority'     => $result['Authority'],
					'Name'          => $Name,
					'AmountTomaan'  => $SendAmount,
					'Mobile'        => $Mobile,
					'Email'         => $Email,
					'InputDate'     => current_time( 'mysql' ),
					'Description'   => $Description,
					'Status'        => 'SEND'
        ),array(
          '%s',
          '%s',
          '%d',
          '%s',
          '%s',
          '%s',
          '%s',
          '%s'
        ));
        
        //Header('Location: https://www.zarinpal.com/pg/StartPay/'.$result['Authority']);
                    
        $Location = 'https://www.zarinpal.com/pg/StartPay/'.$result['Authority'];

        return "<script>document.location = '${Location}'</script><center>در صورتی که به صورت خودکار به درگاه بانک منتقل نشدید <a href='${Location}'>اینجا</a> را کلیک کنید.</center>";
      } 
      else 
      {
        $error .= EZD_GetResaultStatusString($result['Status']) . "<br>\r\n";
      }
    }
  }
  //// END REQUEST
  
  
  ////////////////////////////////////////////////////
  ///             RESPONSE
  if(isset($_GET['Authority']))
  {

    
    $Authority = filter_input(INPUT_GET, 'Authority', FILTER_SANITIZE_SPECIAL_CHARS);
    
    if($_GET['Status'] == 'OK'){
        
      $Record = EZD_GetDonate($Authority);
      if( $Record  === false)
      {
        $error .= 'چنین تراکنشی در سایت ثبت نشده است' . "<br>\r\n";
      }
      else
      {

          $data = array('MerchantID' => $MerchantID, 'Authority' => $Record['Authority'], 'Amount' => $Record['AmountTomaan']);
          $jsonData = json_encode($data);
          $ch = curl_init('https://www.zarinpal.com/pg/rest/WebGate/PaymentVerification.json');
          curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
          curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_HTTPHEADER, array(
              'Content-Type: application/json',
              'Content-Length: ' . strlen($jsonData)
          ));
          $result = curl_exec($ch);
          $err = curl_error($ch);
          curl_close($ch);
          $result = json_decode($result, true);

              if ($result['Status'] == 100) {

          EZD_ChangeStatus($Authority, 'OK');
          $message .= get_option( 'EZD_IsOk') . "<br>\r\n";
          $message .= 'کد پیگیری تراکنش:'. $result['RefID'] . "<br>\r\n";
          
          $EZD_TotalAmount = get_option("EZD_TotalAmount");
          update_option("EZD_TotalAmount" , $EZD_TotalAmount + $Record['AmountTomaan']);
        } 
        else 
        {
          EZD_ChangeStatus($Authority, 'ERROR');
          $error .= get_option( 'EZD_IsError') . "<br>\r\n";
          $error .= EZD_GetResaultStatusString($result['Status']) . "<br>\r\n";
        }
      }
    } 
    else
    {
      $error .= 'تراکنش توسط کاربر بازگشت خورد';
      EZD_ChangeStatus($Authority, 'CANCEL');
    }
  }
  ///     END RESPONSE
  
  $style = '';
  
  if(get_option('EZD_UseCustomStyle') == 'true')
  {
    $style = get_option('EZD_CustomStyle');
  }
  else
  {
    $style = '#EZD_MainForm {  width: 400px;  height: auto;  margin: 0 auto;  direction: rtl; }  #EZD_Form {  width: 96%;  height: auto;  float: right;  padding: 10px 2%; }  #EZD_Message,#EZD_Error {  width: 90%;  margin-top: 10px;  margin-right: 2%;  float: right;  padding: 5px 2%;  border-right: 2px solid #006704;  background-color: #e7ffc5;  color: #00581f; }  #EZD_Error {  border-right: 2px solid #790000;  background-color: #ffc9c5;  color: #580a00; }  .EZD_FormItem {  width: 90%;  margin-top: 10px;  margin-right: 2%;  float: right;  padding: 5px 2%; }    .EZD_FormLabel {  width: 35%;  float: right;  padding: 3px 0; }  .EZD_ItemInput {  width: 64%;  float: left; }  .EZD_ItemInput input {  width: 90%;  float: right;  border-radius: 3px;  box-shadow: 0 0 2px #00c4ff;  border: 0px solid #c0fff0;  font-family: inherit;  font-size: inherit;  padding: 3px 5px; }  .EZD_ItemInput input:focus {  box-shadow: 0 0 4px #0099d1; }  .EZD_ItemInput input.error {  box-shadow: 0 0 4px #ef0d1e; }  input.EZD_Submit {  background: none repeat scroll 0 0 #2ea2cc;  border-color: #0074a2;  box-shadow: 0 1px 0 rgba(120, 200, 230, 0.5) inset, 0 1px 0 rgba(0, 0, 0, 0.15);  color: #fff;  text-decoration: none;  border-radius: 3px;  border-style: solid;  border-width: 1px;  box-sizing: border-box;  cursor: pointer;  display: inline-block;  font-size: 13px;  line-height: 26px;  margin: 0;  padding: 0 10px 1px;  margin: 10px auto;  width: 50%;  font: inherit;  float: right;  margin-right: 24%; }';
  }
  
  
	$out = '
  <style>
    '. $style . '
  </style>
      <div style="clear:both;width:100%;float:right;">
	        <div id="EZD_MainForm">
          <div id="EZD_Form">';
          
if($message != '')
{    
    $out .= "<div id=\"EZD_Message\">
    ${message}
            </div>";
}

if($error != '')
{    
    $out .= "<div id=\"EZD_Error\">
    ${error}
            </div>";
}

     $out .=      '<form method="post">
              <div class="EZD_FormItem">
                <label class="EZD_FormLabel">مبلغ :</label>
                <div class="EZD_ItemInput">
                  <input style="width:60%" type="text" name="EZD_Amount" value="'. $Amount .'" />
                  <span style="margin-right:10px;">'. $EZD_Unit .'</span>
                </div>
              </div>
              
              <div class="EZD_FormItem">
                <label class="EZD_FormLabel">نام و نام خانوادگی :</label>
                <div class="EZD_ItemInput"><input type="text" name="EZD_Name" value="'. $Name .'" /></div>
              </div>
              
              <div class="EZD_FormItem">
                <label class="EZD_FormLabel">تلفن همراه :</label>
                <div class="EZD_ItemInput"><input type="text" name="mobile" value="'. $Mobile .'" /></div>
              </div>
              
              <div class="EZD_FormItem">
                <label class="EZD_FormLabel">ایمیل :</label>
                <div class="EZD_ItemInput"><input type="text" name="email" style="direction:ltr;text-align:left;" value="'. $Email .'" /></div>
              </div>
              
              <div class="EZD_FormItem">
                <label class="EZD_FormLabel">توضیحات :</label>
                <div class="EZD_ItemInput"><input type="text" name="EZD_Description" value="'. $Description .'" /></div>
              </div>
              
              <div class="EZD_FormItem">
                <input type="submit" name="submit" value="پرداخت" class="EZD_Submit" />
              </div>
              <!--

                //////////////////////////////////
                //                              //
                //    Erima Zarrinpal Donate    //
                //        By Seyyed AMir        //
                //      http://www.DBlog.ir     //
                //                              //
                //////////////////////////////////
            -->
              
            </form>
          </div>
        </div>
      </div>
	';
  
  return $out;
}

/////////////////////////////////////////////////
// تنظیمات اولیه در هنگام اجرا شدن افزونه.
register_activation_hook(__FILE__,'EriamZarinpalDonate_install');
function EriamZarinpalDonate_install()
{
	EZD_CreateDatabaseTables();
}
function EZD_CreateDatabaseTables()
{
		global $wpdb;
		$erimaDonateTable = $wpdb->prefix . TABLE_DONATE;
		// Creat table
		$nazrezohoor = "CREATE TABLE IF NOT EXISTS `$erimaDonateTable` (
					  `DonateID` int(11) NOT NULL AUTO_INCREMENT,
					  `Authority` varchar(50) NOT NULL,
					  `Name` varchar(50) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
					  `AmountTomaan` int(11) NOT NULL,
					  `Mobile` varchar(11) ,
					  `Email` varchar(50),
					  `InputDate` varchar(20),
					  `Description` varchar(100) CHARACTER SET utf8 COLLATE utf8_persian_ci,
					  `Status` varchar(5),
					  PRIMARY KEY (`DonateID`),
					  KEY `DonateID` (`DonateID`)
					) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
		dbDelta($nazrezohoor);
		// Other Options
		add_option("EZD_TotalAmount", 0, '', 'yes');
		add_option("EZD_TotalPayment", 0, '', 'yes');
		add_option("EZD_IsOK", 'با تشکر پرداخت شما به درستی انجام شد.', '', 'yes');
		add_option("EZD_IsError", 'متاسفانه پرداخت انجام نشد.', '', 'yes');
    
    $style = '#EZD_MainForm {
  width: 400px;
  height: auto;
  margin: 0 auto;
  direction: rtl;
}

#EZD_Form {
  width: 96%;
  height: auto;
  float: right;
  padding: 10px 2%;
}

#EZD_Message,#EZD_Error {
  width: 90%;
  margin-top: 10px;
  margin-right: 2%;
  float: right;
  padding: 5px 2%;
  border-right: 2px solid #006704;
  background-color: #e7ffc5;
  color: #00581f;
}

#EZD_Error {
  border-right: 2px solid #790000;
  background-color: #ffc9c5;
  color: #580a00;
}

.EZD_FormItem {
  width: 90%;
  margin-top: 10px;
  margin-right: 2%;
  float: right;
  padding: 5px 2%;
}

.EZD_FormLabel {
  width: 35%;
  float: right;
  padding: 3px 0;
}

.EZD_ItemInput {
  width: 64%;
  float: left;
}

.EZD_ItemInput input {
  width: 90%;
  float: right;
  border-radius: 3px;
  box-shadow: 0 0 2px #00c4ff;
  border: 0px solid #c0fff0;
  font-family: inherit;
  font-size: inherit;
  padding: 3px 5px;
}

.EZD_ItemInput input:focus {
  box-shadow: 0 0 4px #0099d1;
}

.EZD_ItemInput input.error {
  box-shadow: 0 0 4px #ef0d1e;
}

input.EZD_Submit {
  background: none repeat scroll 0 0 #2ea2cc;
  border-color: #0074a2;
  box-shadow: 0 1px 0 rgba(120, 200, 230, 0.5) inset, 0 1px 0 rgba(0, 0, 0, 0.15);
  color: #fff;
  text-decoration: none;
  border-radius: 3px;
  border-style: solid;
  border-width: 1px;
  box-sizing: border-box;
  cursor: pointer;
  display: inline-block;
  font-size: 13px;
  line-height: 26px;
  margin: 0;
  padding: 0 10px 1px;
  margin: 10px auto;
  width: 50%;
  font: inherit;
  float: right;
  margin-right: 24%;
}';
  add_option("EZD_CustomStyle", $style, '', 'yes');
  add_option("EZD_UseCustomStyle", 'false', '', 'yes');
}

function EZD_GetDonate($Authority)
{
  global $wpdb;
  $Authority = strip_tags($wpdb->escape($Authority));
  
  if($Authority == '')
    return false;
  
	$erimaDonateTable = $wpdb->prefix . TABLE_DONATE;

  $res = $wpdb->get_results( "SELECT * FROM ${erimaDonateTable} WHERE Authority = '${Authority}' LIMIT 1",ARRAY_A);
  
  if(count($res) == 0)
    return false;
  
  return $res[0];
}

function EZD_AddDonate($Data, $Format)
{
  global $wpdb;

  if(!is_array($Data))
    return false;
  
	$erimaDonateTable = $wpdb->prefix . TABLE_DONATE;

  $res = $wpdb->insert( $erimaDonateTable , $Data, $Format);
  
  if($res == 1)
  {
    $totalPay = get_option('EZD_TotalPayment');
    $totalPay += 1;
    update_option('EZD_TotalPayment', $totalPay);
  }
  
  return $res;
}

function EZD_ChangeStatus($Authority,$Status)
{
  global $wpdb;
  $Authority = strip_tags($wpdb->escape($Authority));
  $Status = strip_tags($wpdb->escape($Status));
  
  if($Authority == '' || $Status == '')
    return false;
  
	$erimaDonateTable = $wpdb->prefix . TABLE_DONATE;

  $res = $wpdb->query( "UPDATE ${erimaDonateTable} SET `Status` = '${Status}' WHERE `Authority` = '${Authority}'");
  
  return $res;
}

function EZD_GetResaultStatusString($StatusNumber)
{
  switch($StatusNumber)
  {
    case -1:
      return 'اطلاعات ارسال شده ناقص است';
    case -2:
      return 'IP و یا مرچنت کد پذیرنده صحیح نیست';
    case -3:
      return 'رقم باید بالای صد تومان باشد';
    case -4:
      return 'سطح تایید پذیرنده پایین تر از سطح نقره ای است';
    case -11:
      return 'درخواست مورد نظر یافت نشد';
    case -21:
      return 'هیچ نوع عملیات مالی برای این تراکنش یافت نشد';
    case -22:
      return 'تراکنش نا موفق می باشد';
    case -33:
      return 'رقم تراکنش با رقم پرداخت شده مطابقت ندارد';
    case -54:
      return 'درخواست مورد نظر آرشیو شده';
    case 100:
      return 'عملیات با موفقیت انجام شد';
    case 101:
      return 'عملیات این تراکنش با موفقیت انجام شد ولی قبلا عملیات اعتبار سنجی بر روی این تراکنش انجام شده است';
  }
  
  return '';
}

function EZD_GetCallBackURL()
{
  $pageURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
  
  $ServerName = htmlspecialchars($_SERVER["SERVER_NAME"], ENT_QUOTES, "utf-8");
  $ServerPort = htmlspecialchars($_SERVER["SERVER_PORT"], ENT_QUOTES, "utf-8");
  $ServerRequestUri = htmlspecialchars($_SERVER["REQUEST_URI"], ENT_QUOTES, "utf-8");
  
  if ($_SERVER["SERVER_PORT"] != "80")
  {
      $pageURL .= $ServerName .":". $ServerPort . $_SERVER["REQUEST_URI"];
  } 
  else 
  {
      $pageURL .= $ServerName . $ServerRequestUri;
  }
  return $pageURL;
}

?>