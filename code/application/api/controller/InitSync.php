<?php
namespace app\api\controller;

class InitSync {
    //全量数据同步给人脸识别服务
    // /api/init_sync
    public function index(){
        $face_types = $this->face_type();
        $_arrs = db('face')->field('id,name,type_id,status,number')->where([
            'delete_time' => 0
        ])->order('id asc')->select();
        $arrs = [];
        foreach($_arrs as $row){
            $row['type_name'] = $face_types['x'.$row['type_id']];
            $row['photo'] = $this->face_photo($row['id']);
            unset($row['id']);
            $arrs[] = $row;
        }
        unset($_arrs);
        if($ip = request()->ip()){
            db('serve')->where([
                'serve_ip' => $ip
            ])->setField('sync_time',time());
        }
        return [
            'code' => 1,  // 0=失败  1=成功
            'data' => $arrs,
            'message' => 'success'
        ];
    }
    private function face_type(){
        $_arrs = db('face_type')->field('id,name')->order('id asc')->select();
        $face_types = [];
        foreach ($_arrs as $row) {
            $face_types['x'.$row['id']] = $row['name'];
        }
        return $face_types;
    }
    private function face_photo($face_id){
        return db('face_photo')->where([
            'face_id' => $face_id
        ])->order('id asc')->column('photo_url');
    }
}