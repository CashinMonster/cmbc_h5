<?php
namespace Home\Controller;
use Think\Controller;
class RunController extends Controller
{
    public function cancleLock(){
        $userModel = D('user');
        $ProductCodeModel = M('product_code');
        $time = time()-3600;
        $user = $userModel->where('locktime < %d and locktime > 0',$time)->select();
        foreach ($user as $k => $v) {
          $update_data['locktime']  = 0;
          $update_data['productid'] = '';
          $update_data['state']     = 0;
          $state = $userModel->where('id=%d',$v['id'])->save($update_data);
          if($state){
            $data['updatetime'] = date('Y-m-d H:i:s');
            $data['state']      = 0;
            $ProductCodeModel->where('id = %d',$v['productid'])->save($data);
          }
        }
    }
}
