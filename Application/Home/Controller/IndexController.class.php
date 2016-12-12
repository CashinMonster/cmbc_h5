<?php
namespace Home\Controller;

use Think\Controller;
use Common\Api\WxApi;
use Common\Api\Aes;
class IndexController extends Controller
{
    private $wxApi;             //微信接口api
    private $platform_config;
    private $check_mode = 1;    //验证用户是否通过uid进行接口验证
    function __construct()
    {
        parent::__construct();
        $this->redis = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_DEFAULT'),C('REDIS_PORT_DEFAULT'),C('REDIS_AUTH_DEFAULT'));

        // if ( DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE) {
          $this->wxApi = new WxApi(C('APP_ID'), C('APP_SECRET'));
          if(!isset($_SESSION['openid'])||empty($_SESSION['openid'])){
              $_SESSION['openid'] = $this->wxApi->getOpenid();
          }
          /*微信分享签名-----start*/
          $sign_package = $this->wxApi->getSignPackage();
          $this->assign('sign_package', $sign_package);
          if ( DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == TRUE) {
          $share = array( 'title'=>'我这位多年的土豪朋友过生日送大礼了！见者有份',
                          'desc'=>'民生20周年，普天同庆之际不忘给您送上缤纷大礼！',
                          'link'=>HOST_URL.U('index/entry',array('openid'=>$_SESSION['openid'])),
                          'imgUrl'=>RESOURCE_PATH.'/Application/Home/View/Resource/Index/images/success/shares.jpg');
          }else{
            $share = array( 'title'=>'我这位多年的土豪朋友过生日送大礼了！见者有份',
                            'desc'=>'民生20周年，普天同庆之际不忘给您送上缤纷大礼！',
                            'link'=>HOST_URL.U('index/entry',array('openid'=>$_SESSION['openid'])),
                            'imgUrl'=>RESOURCE_PATH.'/Public/cmbc/images/success/shares.jpg');
          }
          $this->assign('SHARE', $share);
          /*微信分享签名-----end*/
        // }
        $userModel   =  D('user');
        //获取openid
        if(!isset($_SESSION['openid'])||empty($_SESSION['openid'])){
        //  if ( DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE) {
            $_SESSION['openid'] = $this->wxApi->getOpenid();
      //    }else{
        //    $_SESSION['openid'] = '123';
        //  }
        }
        if(empty($_SESSION['openid'])){
            $this->error('您查看网页已过期，请重新打开入口链接');
        }
        //获取用户信息存入session
        $user_info = $userModel->where("openid='%s'",$_SESSION['openid'])->find();
        if($user_info){
          //信息保存到session
          $_SESSION['id']         = isset($user_info['id'])?$user_info['id']:null;
          $_SESSION['tel']        = isset($user_info['tel'])?$user_info['tel']:null;
          $_SESSION['productid']  = isset($user_info['productid'])?$user_info['productid']:null;
          $_SESSION['cardno']     = isset($user_info['cardno'])?$user_info['cardno']:null;
          //更新浏览时间
          $update_data['browsetime'] = date("Y-m-d H:i:s");
          $userModel->where("openid = '%s'",$_SESSION['openid'])->save($update_data);
        }else{
          //新增用户
          $user_info['openid']     = $_SESSION['openid'];
          $user_info['createtime'] = date("Y-m-d H:i:s");
          $user_info['browsetime'] = date("Y-m-d H:i:s");
          $id = $userModel->add($user_info);
          $_SESSION['id'] = $id;
        }
          //若是分享入口进入,记录
        if($shareopenid = I('param.openid',null)){
            $ShareModel  = D('share');
            $ShareRecord = D('share_record');
            $state = $ShareRecord->where("openid = '%s' and inviteopenid = '%s'",$_SESSION['openid'],$shareopenid)->find();
            if(empty($state)){
              $state = $ShareModel->where("openid='%s'",$shareopenid)->setInc('click');
              if($state){
              $data['openid'] = $_SESSION['openid'];
              $data['inviteopenid'] = $shareopenid;
              $data['createtime'] = date("Y-m-d H:i:s");
              $data['browsetime'] = date("Y-m-d H:i:s");
              $ShareRecord->data($data)->add();
              doLog('share', "分享被点击",'被分享人:'.$_SESSION['openid'].'分享人:'.$shareopenid,'', $this->redisLog);
              }
            }
        }
    }
    //入口动画
    public function entry(){
      $is_prize = json_decode($this->checkPrize(),true);
      if($is_prize['state']!=1){
        //若已领奖则获取礼包信息
        $this->display('opening');
        exit;
      }else{
        $group = D('product_code')->where("id = %d",$_SESSION['productid'])->find();       
        switch($group['groupid']){
         case 1: $pic_name = 'mo';break;
         case 2: $pic_name = 'ttgy';break;
         case 3: $pic_name = 'xiaomi';break;
         case 4: $pic_name = 'huawei';break;
        }
         $this->assign('picname',$pic_name);
        $this->display('choujiang');//如果是待领奖则直接到领奖页
      }
    }
    //首页
    public function index(){

      //跑马灯
      $show_data = $this->redis->hget('cmbc_show_data');
      if(empty(json_decode($show_data,true))){
        $userModel = D('user');
        //$show_data = $userModel->where('state = 2')->limit(10)->order('browsetime desc')->select();
        //先取一二等奖的用户，以productid作为区分奖项的标准
        $show_data_1 = $userModel->where('state = 2 and productid<8')->limit(7)->order('browsetime desc')->select();
        //先取三四等奖的用户
        $show_data_2 = $userModel->where('state = 2 and productid>8')->limit(10)->order('browsetime desc')->select();
        $show_data = array_merge($show_data_1, $show_data_2);
        shuffle($show_data);
        
        $this->redis->hset('cmbc_show_data',json_encode($show_data));
        $this->redis->expire('cmbc_show_data',15*60);
      }else{
        $show_data = json_decode($show_data,true);
      }
      $ProductCodeModel = M('product_code');
      foreach ($show_data as $k => $v) {
        $product_info = $ProductCodeModel->where('id = %d',$v['productid'])->find();
        $data[$k]['telephone'] = substr_replace($v['telephone'],'****',3,4);
        $data[$k]['name']   = $product_info['productname1'];
      }
      $this->assign('show',$data);
      //判断领奖状态
      $is_prize = json_decode($this->checkPrize(),true);
        //若已领奖则获取礼包信息
        if($is_prize['state']==2){
          $pressnt = json_decode($this->getPresent(),true);
          $this->assign('present',$pressnt);
        }else{
          $this->assign('present','');
        }
        $this->display('index');

    }

    //领奖
    public function login(){
      //获取参数
      $tel  = I('param.tel',null);
      $card = I('param.card',null);
      $userModel = D('user');
      $ProductCodeModel = M('product_code');
      $ProductGroup     = D('product_group');
      //验证卡号
      if($tel&&$card){
        //存入session
        $_SESSION['tel']     = $tel;
        $_SESSION['cardno']  = $card;
        //判断卡号是否已经领奖，去重
        $userstatus = $userModel->where("cardno='%s'", $card)->field('state')->find();
        if ( ($userstatus['state'] == '1') || ($userstatus['state'] == '2') ){
        	doLog('Member/login', "会员登录-卡号已经被使用",'',$card, $this->redisLog);
        	$dataJson = array('state' => 0, 'msg' => '对不起，您输入的卡号已经被使用.');
        	$this->ajaxReturn($dataJson);
        }
        
        //民生银行验证
         $url    = 'https://www.msjyw.com.cn/api/index.php?flow=check&ac=index&cardnum='.$card.'&phone='.$tel;
         $result = json_decode(send_curl($url),true);
         if($result['result']=='0'){
            //保存用户信息
            $data['telephone']  = $tel;
            $data['cardno']     = $card;
            $data['browsetime'] = date("Y-m-d H:i:s");
            $data['locktime']   = 0;
            $data['state']      = 2;
            if($_SESSION['openid']){
              //修改user表中用户中奖状态,并解锁
              $userModel->where("openid='%s'", $_SESSION['openid'])->save($data);
              //修改商品状态
              $ProductCodeModel->where('id=%d',$_SESSION['productid'])->data(array('state' => 2 ))->save();
              //获取商品详细信息及礼包信息
              $product_code = $ProductCodeModel->where('id=%d',$_SESSION['productid'])->find();
              $product_group = $ProductGroup->where('id=%d',$product_code['groupid'])->find();
              //更新礼包销售量
              $ProductGroup->where('id=%d',$product_code['groupid'])->setInc('salequantity');
              $ProductGroup->where('id=%d',$product_code['groupid'])->data(array('updatetime' => date("Y-m-d H:i:s")))->save();
              //拼接短信
              $msg = $product_group['msgtmp'];
              for($i = 1 ; $i <= $product_group['count'] ; $i++){
                $msg = str_replace('{couponcode'.$i.'}', $product_code['couponcode'.$i],$msg);
              }
              //发送短信
              $return_sms['msgreturn'] = send_msg($tel, $msg);
              $userModel->where("id = '%s'",$_SESSION['id'])->save($return_sms);

              //如果是天天果园,则发送请求
              if($product_code['groupid']=2){
                $this->ttgy($tel);
              }


              $dataJson = array('state' => 1, 'msg' => '亲爱的客户，您已成功领取优惠券，稍后会短信通知您，也可至【个人中心】查看。');
              doLog('Member/doOrder', "用户下订单-成功", $_SESSION['productid'],'', $this->redisLog);
              $this->ajaxReturn($dataJson);//exit(json_encode($dataJson));
            }
          }else{
            doLog('Member/login', "会员登录-失败",'','', $this->redisLog);
            $dataJson = array('state' => 2, 'msg' => '对不起，您输入的卡号有误，如还未开通民生直销银行电子账户，请点击开通.');
          }
        }
      $this->ajaxReturn($dataJson);
    }


    //抽奖
    public function getPrize(){
      $UserModel        = D('user');
      $ProductGroup     = D('product_group');
      $ProductCodeModel = M('product_code');
      $check = json_decode($this->checkPrize(),true);
      if($check['state']!=0){
        //如果是已领取的用户,直接跳转到成功页面
        if($check['state']==2){
          $pressnt = json_decode($this->getPresent(),true);
          $this->assign('present',$pressnt);
          $this->display('success');
        }else{
          $group = D('product_code')->where("id = %d",$_SESSION['productid'])->find();
          switch($group['groupid']){
           case 1: $pic_name = 'mo';break;
           case 2: $pic_name = 'ttgy';break;
           case 3: $pic_name = 'xiaomi';break;
           case 4: $pic_name = 'huawei';break;
          }
          $this->assign('picname',$pic_name);
          //如果是未抽奖用户,跳转到抽奖页面
          $product = $ProductCodeModel->where('id=%d',$_SESSION['productid'])->find();
          $this->assign('groupid',$product['id']);
          $this->display('choujiang');
        }
        exit;
      }
      //获取剩余礼包

      $product_group = $ProductGroup->where('salequantity < quantity and state = 1')->select();
       $first_state = 0;

      foreach($product_group as $k=>$v){
	if($v['id']==3||$v['id']==4){
	  $first_state = $v['id'];
              }
	
      }
     $first_info = $ProductCodeModel->where("groupid=%d and state = 0",$first_state)->find();	
	  //判断是否有一等奖和二等奖
	  if($first_info){
			$group_id =  $first_state;
			//获取礼包
			$product_info  = $ProductCodeModel->where("groupid=%d and state=0", $group_id)->lock(true)->find();
				//更新礼包状态
			if($product_info)
			$code_state    = $ProductCodeModel->where("id=%d and state=0", $product_info['id'])->lock(true)->data(array('state' => 1, 'updatetime' => date('Y-m-d H:i:s')))->save();

	  }else{
		    if(count($product_group)>0){
				if(count($product_group)==1){
				  $group_id = $product_group[0]['id'] ;
				}else{
					//20:1,0为3等奖
          $id = array(2,2,2,2,2,2,2,2,2,2,2,2,2,2,2,2,2,2,2,2,1);
				  $group_id    = $id[array_rand($id)];
				}
				//获取礼包
				$product_info  = $ProductCodeModel->where("groupid=%d and state=0", $group_id)->lock(true)->find();
				//更新礼包状态
				if($product_info)
				$code_state    = $ProductCodeModel->where("id=%d and state=0", $product_info['id'])->lock(true)->data(array('state' => 1, 'updatetime' => date('Y-m-d H:i:s')))->save();
			}else{
				doLog('getPrize', "领取失败/全部领完",'','', $this->redisLog);
				$this->display('fail');
				exit;
			}
	  }

      //如果更新礼包状态成功,更新用户信息
      if($code_state)
      $UserModel->where("openid = '%s'",$_SESSION['openid'])->data(array('state' => 1,'productid' => $product_info['id'],'locktime'=>time()))->save();
      else{
         doLog('getPrize', "领取失败",'','', $this->redisLog);
         $this->display('fail');
         exit;
      }
      //奖品名
	  switch($group_id){
		 case 1: $pic_name = 'mo';break;
		 case 2: $pic_name = 'ttgy';break;
		 case 3: $pic_name = 'xiaomi';break;
		 case 4: $pic_name = 'huawei';break;
	  }
      $this->assign('picname',$pic_name);
      $this->display('choujiang');
    }
    //判断用户抽奖状态
    public function checkPrize(){
      $userModel = D('user');
      $user = $userModel->where("openid='%s'", $_SESSION['openid'])->find();
      if($user['state']=='0'){
        $dataJson = array('state' => 0, 'msg' => '未抽奖');
      }else if($user['state']=='2'){
        $dataJson = array('state' => 2, 'msg' => '已领奖');
      }else{
        $dataJson = array('state' => 1, 'msg' => '未领奖');
      }
        return json_encode($dataJson);
    }
    //处理商品详情
    public function getPresent(){
        $id     = $_SESSION['id'];
        $userModel = D('user');
        $user = $userModel->where("openid='%s'", $_SESSION['openid'])->find();
        //未领奖
        if(empty($user['productid'])||$user['state']<2){
          doLog('getPresent', "返回中奖商品失败",'','', $this->redisLog);
          return false;
        }else{//获取商品详情
          $ProductGroup     = D('product_group');
          $ProductCodeModel = M('product_code');

          $product  = $ProductCodeModel->where("id=%d",$_SESSION['productid'])->find();
          $group    = $ProductGroup->where("id=%d",$product['groupid'])->find();
          for($i = 1 ; $i <= $group['count'] ; $i++){
            $result[$i]['id']   = $i;
            $result[$i]['name'] = $product['productname'.$i];
            $result[$i]['url']  = $product['producturl'.$i];
            $result[$i]['code'] = empty($product['couponcode'.$i])?null:$product['couponcode'.$i];
            $result[$i]['img']  = $product['imgname'.$i];
            $result[$i]['offlinetime'] = $group['offlinetime'];
            $result[$i]['gid']  = $product['groupid'];
          }
        }
        doLog('getPresent', "返回中奖商品",json_encode ($result),'', $this->redisLog);
        return json_encode ($result);
    }

    public function success(){
      $pressnt = json_decode($this->getPresent(),true);
      $this->assign('present',$pressnt);
      $this->display('success');
    }

    public function share(){
      if($openid = $_SESSION['openid']){
        $ShareModel = D('share');
        $state =  $ShareModel->where("openid='%s'",$openid)->find();
        if($state){
          $ShareModel->where("openid='%s'",$openid)->setInc('share');
          $ShareModel->where("openid='%s'",$openid)->data(array('updatetime'=> date('Y-m-d H:i:s')))->save();
        }else{
          $data['createtime'] = date('Y-m-d H:i:s');
          $data['updatetime'] = date('Y-m-d H:i:s');
          $data['openid']     = $openid;
          $ShareModel->data($data)->add();
        }
      }else{
          doLog('share', "记录分享失败",'','', $this->redisLog);
      }
    }


    public function man(){
      $this->display('man');
    }
    public function woman(){
      $this->display('woman');
    }
    public function libao(){
      $check = json_decode($this->checkPrize(),true);
      //如果是已领取的用户,直接跳转到成功页面
      if($check['state']==2){
		      $this->success();
        //$pressnt = json_decode($this->getPresent(),true);
        //$this->assign('present',$pressnt);
        //$this->display('success');
        exit;
      }
      $this->display('libao');
    }
    public function moinfo(){
      $this->display('moInfo');
    }
    public function ttgy($tel){
      if ( DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == TRUE) {
        $url = 'http://staging.nirvana.fruitday.com/openApi';
      }else{
        $url = 'http://nirvana.fruitday.com/openApi';
      }
      $secret = 'd50b6a5ff6ff4a3j814y6f6b97ec62ab';
      $params = array(
                    'timestamp'=>time(),
                    'service'=>'open.getMsgifts',
                    'phone'=>$tel
                  );
      ksort($params);
      $query = '';
      foreach($params as $k=>$v){
          $query .= $k.'='.$v.'&';
      }
      $params['sign'] = md5(substr(md5($query.$secret), 0,-1).'w');
      $res = send_curl($url,$params,'POST');
      doLog('Member/ttgy发送请求', "天天果园接口返回结果", $_SESSION['productid'],$res, $this->redisLog);
    }
    public function error(){
      $this->display('fail');
    }
}
