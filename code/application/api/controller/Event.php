<?php
namespace app\api\controller;

class Event {
    //事件列表
    // /api/event?page=1
    public function index(){
        list($serves,$serves_x) = $this->serve();
        $data = [];
        $data['list'] = db('event')->field('id,serve_id,status,time,face,people,result')->order('id desc')->paginate(20)->each(function($row,$key) use($serves_x){
            $row['door_name'] = $serves_x['x'.$row['serve_id']];
            $row['time'] = date('Y-m-d H:i:s',$row['time']);
            switch($row['status']){
                case 1:
                    $row['status'] = 1;
                    $row['status_name'] = '通行';
                    break;
                case 2:
                    $row['status'] = 2;
                    $row['status_name'] = '禁行';
                    break;
                case 3:
                    $row['status'] = 1;
                    $row['status_name'] = '通行';
                    break;
                case 4:
                    $row['status'] = 2;
                    $row['status_name'] = '禁行';
                    break;
                default:
                    $row['status'] = 0;
                    $row['status_name'] = '未知';
            }
            unset($row['serve_id']);
            return $row;
        })->toArray();
        $data['serves'] = $serves;
        return [
            'code' => 1,  // 0=失败  1=成功
            'data' => $data,
            'message' => 'success'
        ];
    }
    private function serve(){
        $serves = db('serve')->field('id,door_name')->order('id asc')->select();
        $serves_x = [];
        foreach ($serves as $row) {
            $serves_x['x'.$row['id']] = $row['door_name'];
        }
        return [
            $serves,
            $serves_x
        ];
    }

    //事件信息
    // /api/event/show
    public function show(){
        $id = input('post.id/d','');
        if(!$id){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => 'failure'
            ];
        }
        $row = db('event')->field('id,serve_id,time,face,people,result')->where([
            'id' => $id
        ])->find();
        if(!$row){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '事件信息不存在'
            ];
        }
        $row['time'] = date('Y-m-d H:i:s',$row['time']);
        $row['door_name'] = '';
        $serve_ip = '';
        if($row['serve_id']){
            $serve = db('serve')->field('door_name,serve_ip')->where([
                'id' => $row['serve_id']
            ])->find();
            if($serve){
                $row['door_name'] = $serve['door_name'];
                $serve_ip = $serve['serve_ip'];
            }
        }
        $row['video'] = [];
        $event_video = db('event_video')->field('path')->where([
            'event_id' => $row['id']
        ])->order('id asc')->select();
        if($event_video){
            foreach($event_video as $v){
                $row['video'][] = 'http://'.$serve_ip.$v['path'];
            }
        }
        $row['faces'] = [];
        $event_info = db('event_info')->field('name,type_name,status')->where([
            'event_id' => $row['id']
        ])->order('id asc')->select();
        if($event_info){
            $infos = [];
            foreach($event_info as $info){
                $info['status_name'] = ($info['status']==1?'通行':'禁行');
                $row['faces'][] = $info;
            }
        }
        unset($row['serve_id']);
        return $row;
    }
    //接收事件信息
    // /api/event/receive
    public function receive(){
        $_data = input('post.data','');
        $data = json_decode(base64_decode(str_replace(' ','+',urldecode($_data))),true);
        $_data = null;
        if(empty($data)){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => $_data,
                'message' => '无法解析提交的数据'
            ];
        }
        if(!isset($data['video']) || empty($data['video'])){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => $_data,
                'message' => '视频字段数据缺失'
            ];
        }
        if(!isset($data['status']) || $data['status']!=1 && $data['status']!=2){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => $_data,
                'message' => '请求异常'
            ];
        }
        $serve = db('serve')->field('id,door_config')->where([
            'serve_ip' => request()->ip()
        ])->find();
        if(!$serve){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => $_data,
                'message' => '服务异常'
            ];
        }
        $event_id = db('event')->insertGetId([
            'serve_id' => $serve['id'],
            'status' => $data['status'],
            'time' => strtotime($data['time']),
            'face' => $data['face'],
            'people' => $data['people'],
            'result' => $this->result($data),
            'add_time' => time(),
        ]);
        if(!$event_id){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => $_data,
                'message' => '数据插入失败'
            ];
        }
        //插入视频记录
        $video_sql = [];
        foreach($data['video'] as $path){
            $video_sql[] = [
                'path' => $path,
                'event_id' => $event_id,
            ];
        }
        db('event_video')->insertAll($video_sql);
        //插入人脸信息
        if(!empty($data['infos'])){
            $info_sql = [];
            foreach($data['infos'] as $row){
                $info_sql[] = [
                    'name' => $row['name'],
                    'type_id' => $row['type_id'],
                    'type_name' => $row['type_name'],
                    'status' => $row['status'],
                    'number' => $row['number'],
                    'event_id' => $event_id,
                ];
            }
            db('event_info')->insertAll($info_sql);
        }
        $this->instruct($data['status'],$serve['door_config']); //给门禁发指令
        return [
            'code' => 1,  // 0=失败  1=成功
            'data' => null,
            'message' => 'success'
        ];
    }
    //比对结果
    private function result($data){
        if($data['face'] != $data['people']){
            return '行人数量与人脸数量不一致';
        }
        if(empty($data['infos']) && $data['people']){
            return '共'.$data['people'].'人,全为未知人脸';
        }
        if(!empty($data['infos']) && count($data['infos']) != $data['face']){
            $unstt = $data['face']-count($data['infos']);
            if($unstt>0){
                return '共'.$data['people'].'人,有'.$unstt.'张未知人脸';
            }
        }
        if($data['status']==1){
            return '正常';
        }else if($data['status']==2){
            return '[存在风险]有未授权人员';
        }
        return '未知';
    }
    //人工事件
    // /api/event/artificial
    public function artificial(){
        $serve_id = input('post.serve_id/d','');
        $action = input('post.action/d','');
        if(!$serve_id || ($action!=1 && $action!=2)){  // 1=一键通行  2=一键锁止
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '请求异常'
            ];
        }
        $serve = db('serve')->field('door_config')->where([
            'id' => $serve_id
        ])->find();
        if(!$serve){
            return [
                'code' => 0,  // 0=失败  1=成功
                'data' => null,
                'message' => '服务异常'
            ];
        }
        $this->instruct($action,$serve['door_config']); //给门禁发指令
        $status = ['0','3','4'];
        $result = ['','人工一键通行','人工一键锁止'];
        $time = time();
        db('event')->insertGetId([
            'serve_id' => $serve_id,
            'status' => $status[$action],
            'time' => $time,
            'face' => 0,
            'people' => 0,
            'result' => $result[$action],
            'add_time' => $time,
        ]);
        return [
            'code' => 1,  // 0=失败  1=成功
            'data' => null,
            'message' => 'success'
        ];
    }
    //给门禁发指令
    private function instruct($action,$door_config){  //1=通行  2=锁止

    }
}