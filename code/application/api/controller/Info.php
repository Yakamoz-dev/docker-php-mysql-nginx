<?php
namespace app\api\controller;
use Db;
class Info {
    private $status_name = ['未知','通行','禁行'];
    //人脸列表
    // /api/info?page=1
    public function index(){
        list($face_types,$face_types_x) = $this->face_type();
        $data = [];
        $data['list'] = db('face')->field('id,name,type_id,status,photo_number,note,update_time')->where([
            'delete_time' => 0
        ])->order('id desc')->paginate(20)->each(function($row,$key) use($face_types_x){
            $row['type_name'] = $face_types_x['x'.$row['type_id']];
            $row['status_name'] = $this->status_name[$row['status']];
            $row['update_time'] = date('Y-m-d H:i:s',$row['update_time']);
            return $row;
        })->toArray();
        $data['types'] = $face_types;
        return [
            'code' => 1,  // 0=失败  1=成功
            'data' => $data,
            'message' => 'success'
        ];
    }
    private function face_type(){
        $_arrs = db('face_type')->field('id,name')->order('id asc')->select();
        $face_types = [];
        foreach ($_arrs as $row) {
            $face_types['x'.$row['id']] = $row['name'];
        }
        return [
            $_arrs,
            $face_types
        ];
    }
    //人脸信息
    // /api/info/show
    public function show(){
        $id = input('post.id/d','');
        if(!$id){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => 'failure'
            ];
        }
        $row = db('face')->field('id,name,type_id,status,photo_number,note,update_time,delete_time')->where([
            'id' => $id
        ])->find();
        if(!$row || $row['delete_time']){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '人员信息不存在或已被删除'
            ];
        }
        $row['update_time'] = date('Y-m-d H:i:s',$row['update_time']);
        $row['status_name'] = $this->status_name[$row['status']];
        $row['type_name'] = db('face_type')->where([
            'id' => $row['type_id']
        ])->value('name');
        $row['photos'] = db('face_photo')->where([
            'face_id' => $row['id']
        ])->order('id asc')->column('photo_url');
        unset($row['type_id'],$row['delete_time']);
        return $row;
    }
    //人脸注册
    // /api/info/reg/step/loadData
    // /api/info/reg/step/saveData
    public function reg($step='loadData'){
        /*
        step = loadData
        $_POST['name'] = '张三';
        $_POST['number'] = '331081198601030814';
        */

        /*
        step = saveData
        $_POST['name'] = '张三';
        $_POST['number'] = '331081198601030814';
        $_POST['type_id'] = '1';
        $_POST['note'] = '姓名不能为空姓名不能为空姓名不能为空姓名不能为空姓名';
        $_POST['photos'] = '照片URL1,照片URL2,照片URL3';
        */

        $step = input('post.step','');
        if($step=='loadData'){
            return $this->loadData();
        }else if($step=='saveData'){
            return $this->saveData();
        }else{
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => 'failure'
            ];
        }
    }
    //检测人脸
    // /api/info/check
    public function check(){
        /*
        $_POST['data'] = 图像base64;
        */
        $data = input('post.data','');
        $req = $this->pushInfo('checkFace',[
            'photo' => $data,
        ]);
        if(!$req){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '人脸检测失败'
            ];
        }
        if(!isset($req['success']) || !$req['success']){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => (isset($req['message'])?$req['message']:'error')
            ];
        }
        if(!isset($req['data']) || !$req['data']){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '检测异常'
            ];
        }
        if(!$photo_path = base64_image_content($data,$_SERVER['DOCUMENT_ROOT'].'/photo')){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '人脸检测失败'
            ];
        }
        return [
            'code' => 1,  // 0=失败  1=成功
            'data' => [
                'path' => str_replace($_SERVER['DOCUMENT_ROOT'],'',$photo_path),
                'base64' => 'data:image/jpeg;base64,'.substr($req['data'],2,-1)
            ],
            'message' => 'success'
        ];
    }

    //手动人脸删除
    // /api/info/del
    public function del(){
        /*
        $_POST['id'] = 1;
        */
        $id = input('post.id/d','');
        if(!$id){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => 'failure'
            ];
        }
        $row = db('face')->field('type_id,number')->where([
            'id' => $id
        ])->find();
        if(!$row){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '数据不存在'
            ];
        }
        $type_is_feoptions = db('face_type')->where([
            'id' => $row['type_id']
        ])->value('is_feoptions');
        if($type_is_feoptions!=1){ //不允许删除
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '该人员禁止手动删除'
            ];
        }
        unset($row['type_id']);
        $req = $this->pushInfo('delete',$row);
        if(!$req){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '删除失败,delete'
            ];
        }
        if(!isset($req['success']) || !$req['success']){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => 'insert,error,'.(isset($req['message'])?$req['message']:'')
            ];
        }
        db('face')->where([
            'id' => $id
        ])->setField('delete_time',time());
        return [
            'code' => 1,  // 0=失败  1=成功
            'data' => null,
            'message' => '删除成功'
        ];
    }
    //注册时调用
    private function loadData(){
        $name = input('post.name','','strip_tags,trim');
        $number = input('post.number','','strip_tags,trim');

        if(!$name){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '姓名不能为空'
            ];
        }
        if(!$number){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '身份证号码不能为空'
            ];
        }
        
        $data = [];
        $data['number'] = $number;
        $data['name'] = $name;
        $data['alias_name'] = ''; //曾用名
        $data['type_id'] = 0;
        $data['status'] = 1;
        $data['note'] = '';
        $data['types'] = db('face_type')->field('id,name')->where([
            'is_feoptions' => 1
        ])->order('id asc')->select();

        $row = db('face')->field('name,type_id,status,note')->where([
            'number' => $number
        ])->find();
        if($row){
            if($row['name']!=$name){ //与以前录入的姓名不一样
                $data['alias_name'] = $row['name']; //曾用名
            }
            $data['type_id'] = $row['type_id'];
            $data['status'] = $row['status'];
            $data['note'] = $row['note'];
        }
        return [
            'code' => 1,  // 0=失败  1=成功
            'data' => $data,
            'message' => 'success'
        ];
    }
    //注册时调用
    private function saveData(){
        $name = input('post.name','','strip_tags,trim');
        $number = input('post.number','','strip_tags,trim');
        $type_id = input('post.type_id/d','');
        $status = input('post.status/d','');
        $note = input('post.note','','strip_tags,trim');
        $photos = input('post.photos','','strip_tags');

        if(!$name){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '姓名不能为空'
            ];
        }
        if(!$number){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '身份证号码不能为空'
            ];
        }
        if(!$type_id){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '请选择身份'
            ];
        }
        $row = db('face_type')->field('name,is_feoptions')->where([
            'id' => $type_id
        ])->find();
        if(!$row || $row['is_feoptions']!=1){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '身份选择错误'
            ];
        }
        $type_name = $row['name'];
        if($status!=1 && $status!=2){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '身份权限异常'
            ];
        }
        if($note && mb_strlen($note,'UTF8')>32){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '备注不可超过32个字'
            ];
        }
        if(!$photos){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '照片不能少于3张'
            ];
        }
        $photos = explode(',',$photos);
        $photo_number = count($photos);
        if($photo_number<3){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '照片不能少于3张'
            ];
        }
        $row = db('face')->field('id,name,type_id,status,number,delete_time')->where([
            'number' => $number
        ])->find();
        if(!$row){ //新增
            $face_id = db('face')->insertGetId([
                'name' => $name,
                'type_id' => $type_id,
                'status' => $status,
                'photo_number' => $photo_number,
                'number' => $number,
                'note' => $note,
                'add_time' => time()
            ]);
            if(!$face_id){
                return [
                    'code' => 0,  // 0=失败  1=成功
                    'data' => null,
                    'message' => '保存失败'
                ];
            }
            $is_delete_time = 0;
            $pushInfo_type = 'insert';
        }else{ //更新
            $face_id = $row['id'];
            $is_delete_time = $row['delete_time'];
            $data = [
                'name' => $name,
                'type_id' => $type_id,
                'status' => $status,
                'photo_number' => Db::raw('photo_number+'.$photo_number),
                'number' => $number,
                'note' => $note,
                'update_time' => time(),
                'delete_time' => 0
            ];
            $type_row = db('face_type')->field('name,is_feoptions')->where([
                'id' => $row['type_id']
            ])->find();
            if($type_row && $type_row['is_feoptions']==0){ //不允许修改
                unset($data['name'],$data['type_id'],$data['status'],$data['number']);
                $name = $row['name'];
                $type_id = $row['type_id'];
                $type_name = $type_row['name'];
                $status = $row['status'];
                $number = $row['number'];
            }
            $req = db('face')->where([
                'id' => $face_id
            ])->update($data);
            if(!$req){
                return [
                    'code' => 0,  // 0=失败  1=成功
                    'data' => null,
                    'message' => '保存失败'
                ];
            }
            $pushInfo_type = 'update';
        }
        $this->insert_photo($face_id,$photos); //插入照片
        if($is_delete_time){ //原来是删除状态，需要把以前的图片拿出来
            $photos = db('face_photo')->where([
                'face_id' => $face_id
            ])->order('id asc')->column('photo_url');
        }
        //推送数据，提取照片的人脸特征
        $data = [
            'name' => $name,
            'type_id' => (string)$type_id,
            'type_name' => $type_name,
            'status' => (string)$status,
            'number' => $number,
            'photo' => $photos
        ];
        if($pushInfo_type=='update'){
            $_data = $data;unset($_data['photo']);
            $req = $this->pushInfo('update',$_data);
            if(!$req){
                return [
                    'code' => 0,  // 0=失败  1=成功
                    'data' => null,
                    'message' => '录入失败,update'
                ];
            }
            if(!isset($req['success']) || !$req['success']){
                return [
                    'code' => 0,  // 0=失败  1=成功
                    'data' => null,
                    'message' => 'update,error,'.(isset($req['message'])?$req['message']:'')
                ];
            }
        }
        $req = $this->pushInfo('insert',$data);
        if(!$req){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '录入失败,insert'
            ];
        }
        if(!isset($req['success']) || !$req['success']){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => 'insert,error,'.(isset($req['message'])?$req['message']:'')
            ];
        }
        $message = '全部人脸录入成功';
        if(isset($req['data']) && is_array($req['data'])){
            $message = '部分人脸录入成功,但有'.count($req['data']).'张照片未检测到人脸';
        }
        return [
            'code' => 1,  // 0=失败  1=成功
            'data' => null,
            'message' => $message
        ];
    }
    //注册时调用，插入照片
    private function insert_photo($face_id,$photos){
        $time = time();
        $photo_sql = [];
        foreach($photos as $photo_url){
            $photo_sql[] = [
                'photo_url' => trim($photo_url),
                'add_time' => $time,
                'face_id' => $face_id
            ];
        }
        db('face_photo')->insertAll($photo_sql);
    }
    //注册时调用，推送数据，提取照片的人脸特征，删除
    private function pushInfo($type,$data){
        /*
            $type = insert
                  = update
                  = delete
        */
        $data = json_encode($data,JSON_UNESCAPED_UNICODE);
        $data = base64_encode($data);
        if($type=='insert'){
            $url = 'http://192.168.0.138/todo/api/v1.0/uploadDataByJson';
        }else if($type=='update'){
            $url = 'http://192.168.0.138/todo/api/v1.0/updateByNumber';
        }else if($type=='delete'){
            $url = 'http://192.168.0.138/todo/api/v1.0/deleteByNumber';
        }else if($type=='checkFace'){
            $url = 'http://192.168.0.138/todo/api/v1.0/checkfaceByjson';
        }else{
            return false;
        }
        $header = [];
        $header[] = 'charset: utf-8';
        $header[] = 'Content-Type: application/json';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT,30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'data' => $data
        ]));
        $req = curl_exec($ch);
        if(curl_error($ch)){ //请求出错  包括超时
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        $req = json_decode($req,true);
        if(!is_array($req)){
            return false;
        }
        return $req;
    }
}