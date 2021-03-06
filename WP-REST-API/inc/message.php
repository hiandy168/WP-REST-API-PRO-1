<?php
/*
 * 
 * WordPres 连接微信小程序
 * Author: JIANBO + Denis + 艾码汇
 * github:  https://www.imahui.com
 * 基于 守望轩 WP REST API For App 开源插件定制 , 使用 WPJAM BASIC 框架
 * 
 */
// 发送消息模板 API
add_action( 'rest_api_init', function () {
	register_rest_route( 'wechat/v1', 'message/send', array(
		'methods' => 'POST',
		'callback' => 'sendmessage'
	));
});
function sendmessage($request) {
	$openid=$request['openid'];
    $template_id=$request['template_id'];
    $postid=$request['postid'];
    $form_id=$request['form_id'];
    $total_fee=$request['total_fee'];
	$flag=$request['flag'];
    $fromUser='';
    $parent=0;
    if (isset($request['fromUser'])) {
        $fromUser=$request['fromUser'];
    }
    if (isset($request['parent'])) {
        $parent=(int)$request['parent'];
    }
    if(empty($openid) || empty($template_id) || empty($postid) || empty($form_id) || empty($total_fee) || empty($flag)) {
        return new WP_Error( 'error', 'openid or template_id or postid or form_id  or total_fee or flag is empty', array( 'status' => 500 ) );
    } else if(!function_exists('curl_init')) {
        return new WP_Error( 'error', 'php curl is not enabled ', array( 'status' => 500 ) );
    } else {
        $data=send_message_data($openid,$template_id,$postid,$form_id,$total_fee,$flag,$fromUser,$parent); 
        if (empty($data)) {
            return new WP_Error( 'error', 'get openid error', array( 'status' => 404 ) );
        }  
        // Create the response object
        $response = new WP_REST_Response($data); 
        // Add a custom status code
        $response->set_status( 200 ); 
        // Add a custom header
        return $response;
    }
}
function send_message_data($openid,$template_id,$postid,$form_id,$total_fee,$flag,$fromUser,$parent) {
	$appid = get_setting_option('appid');
    $appsecret = get_setting_option('secretkey');
    $page='';
    if($flag =='1' || $flag=='2') {
        $total_fee= $total_fee.'元';
    }
	if($flag=='1' || $flag=='3') {
        $page='pages/detail/detail?id='.$postid;
    } elseif($flag=='2') {
        $page='pages/about/about?id='.$postid;
    }
	if(empty($appid) || empty($appsecret) ) {
        $result["code"]="success";
        $result["message"]="appid or appsecret is empty";
        $result["status"]="500";                   
        return $result;
    } else {
        $access_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$appsecret;
        $access_result = https_request($access_url);
        if($access_result != "ERROR") {
            $access_array = json_decode($access_result,true);
            if(empty($access_array['errcode'])) {
                $access_token = $access_array['access_token']; 
                $expires_in = $access_array['expires_in'];
                $data = array();
				$data1 = array(
                    "keyword1"=>array(
						"value"=>$total_fee,                     
						"color" =>"#173177"
                    ),
                    "keyword2"=>array(
                        "value"=> get_setting_option('prasie'),
                        "color"=> "#173177"
                    )
                );  
                date_default_timezone_set('PRC');
                $datetime =date('Y-m-d H:i:s');
                $data2 = array(
                    "keyword1"=>array(
                        "value"=>$fromUser,                     
                        "color" =>"#173177"
                    ),
                    "keyword2"=>array(
                        "value"=>$total_fee,
                        "color"=> "#173177"
                    ),
                    "keyword3"=>array(
                        "value"=>$datetime,
                        "color"=> "#173177"
                    )
                );
				if($flag=='1' || $flag=='2') {
                    $postdata['data']=$data1;
                } elseif ($flag=='3') {
                    $postdata['data']=$data2; 
                }
                $postdata['touser']=$openid;
                $postdata['template_id']=$template_id;
                $postdata['page']=$page;
                $postdata['form_id']=$form_id;
                $postdata['template_id']=$template_id;
                $url ="https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=".$access_token;
				$access_result = https_curl_post($url,$postdata,'json');
                if($access_result !="ERROR"){
                    $access_array = json_decode($access_result,true);
                    if($access_array['errcode'] == '0') {
						if($parent  !=0) {
                            $delFlag=delete_comment_meta($parent,"formId",$form_id);
                            if($delFlag) {
								$result["message"]="sent message success,del formId success";  
                            } else {
                                $result["message"]="sent message success,del formId fail"; 
                            }     
                        } else {
                            $result["message"]="sent message  success";
                        }                             
                        $result["code"]="success";                            
                        $result["status"]="200";                   
                        return $result;
                    } else {
                        $result["code"]=$access_array['errcode'];
                        $result["message"]=$access_array['errmsg'];
                        $result["status"]="500";                   
                        return $result;
                    }   
                } else {
                    $result["code"]="success";
                    $result["message"]="https POST request error";
                    $result["status"]="500";                   
                    return $result;
                }
            } else {
                $result["code"]=$access_array['errcode'];
                $result["message"]=$access_array['errmsg'];
                $result["status"]="500";                   
                return $result;
            }   
        } else {
            $result["code"]="success";
            $result["message"]="https request error";
            $result["status"]="500";                   
            return $result;
        }      
    }
}
//发起 POST 请求
function https_curl_post($url,$data,$type){
    if($type=='json'){
        $data=json_encode($data);
    }
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    if (!empty($data)){
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS,$data);
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($curl, CURLOPT_HTTPHEADER, $headers );
    $data = curl_exec($curl);
    if (curl_errno($curl)){
        return 'ERROR';
    }
    curl_close($curl);
    return $data;
}
