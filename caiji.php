<?php
	class caiJi{
		protected $_url = "";
		protected $_path = "";
		protected $data=array();//存放爬取到的数据
        protected $error = array();//存放下载出错的数据('保存路径','保存内容',是否为url)
		public function __construct($url,$path){//采集链接,保存目录
			$this->_url = $url;
			$this->_path = $path;
		}

		public function run(){//开始采集
            //收集数据
			if(!$zJie=$this->getFile($this->_url)){
			    die('章节没有获取到!');
            }
			require_once 'simple_html_dom.php';
			$dom=str_get_html($zJie);
			$arr=$dom->find('.list05_right');// .list05_rightA a
			foreach($arr as $k=>$v){
			    $dom1 = str_get_html($v);
                $title = $this->getTitle($dom1);//标题
                $desc = $this->getDesc($dom1);
                $mp3Url="";
                $data = $this->getList($v,$k.'-'.$title,$mp3Url);//array
                $this->data[]=array(
                    'title'=>$title,
                    'desc'=>$desc,
                    'mp3Url'=>$mp3Url,
                    'data'=>$data
                );
            }
            echo '采集到的信息';
            var_dump($this->data);
            //保存到本地
            echo '<hr/>下载开始';
            $this->createDir($this->_path);//创建下载目录
            foreach($this->data as $k => $v){
                //创建章节目录
                $dir = $this->_path.'/'.($k+1).$v['title'];
                $this->createDir($dir);//创建当前章节目录
                //$this->putFile($dir.'/练习说明.txt',$v['desc']);//保存练习说明
                $this->putUrlFile($dir.'/练习说明.txt',$v['desc']);//保存练习说明
                //$mp3=$this->getFile($v['mp3Url']);
                //$this->putFile($dir.'/练习说明.mp3',$mp3);
                $this->putUrlFile($dir.'/练习说明.mp3',$v['mp3Url'],true);
                foreach($v['data'] as $v1){
                    //保存描述
                    $this->putUrlFile($dir.'/'.$v1[title].'.txt',$v1['desc']);
                    //保存视频
                    $this->putUrlFile($dir.'/'.$v1[title].'.txt',$v1['videoUrl'],true);
                }
                die;//只下载一章的,注释掉可以下载全部
            }
            echo '<hr/>请下载出错的数据';
            var_dump($this->error);//显示出错的数据
		}
		protected function getDesc($obj){
		    $desc = $obj->find('.cuts');
		    return trim($desc[0]->innertext);
        }
        protected function getTitle($obj){//获取标题
            $title = ($obj->find('.weight a'));
            $title = $title[0]->innertext;
            $title=explode('&nbsp',trim($title));
            $title = str_replace('》','',$title[2]);
            return $title;
        }
        protected function getList($obj,$dir,&$mp3Url){//获取章节下的每讲,练习说明MP3每讲的都一样,所以直接引用传值,传给父数组
            $objArr = $obj->find('li a');
            $dataList = array();
            foreach($objArr as $a){
                $data=array();
                $data['title'] = str_replace('&nbsp','-',$a->innertext);//标题
                $url = 'http://singerdream.com'.$a->href;//可以根据这个连接获取到视频地址和描述
                $domObj = file_get_html($url);//视频页面dom对象,curl方式获取不了........
                //获取视频和录音链接
                $domArr = $domObj->find('param[name=flashvars]');//包含视频和录音的数组
                $mp3Url= $this->getNeedBetween($domArr[0]->value,'=','mp3').'mp3';//录音地址
                $data['videoUrl']=$this->getNeedBetween($domArr[2]->value,'=','mp4').'mp4';//视频地址
                //获取描述
                $desc = $domObj->find('.captionNR ');
                $data['desc']=trim($desc[0]->innertext);//视频描述
                //$data['file_dir']=$this->_path.'/'.$dir;//需要下载到的目录
                $dataList[]=$data;
            }
            return $dataList;
        }
		protected function getFile($url){//获取文件(文件链接)
			if(function_exists('curl_init')) {
		        $ch = curl_init();
		        curl_setopt($ch, CURLOPT_URL, $url);
		        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
		        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		        $temp = curl_exec($ch);
		        if(!curl_error($ch)){
		        	return $temp;
		        }
		        return false;
		    }else{
		    	die('请开启curl!');
		    }
		}
		protected function putFile($path,$cotent){//保存文件
            $path = iconv('utf-8', 'gbk', $path);
            if (@file_put_contents($path, $cotent)) {
                return true;
            }
            return false;
		}
		protected function putUrlFile($path,$url,$is_url=false){//保存网络文件(路径,链接或内容,是否是链接)
            if($is_url){//如果是链接
                //获取文件
                if(!$file = $this->getFile($url)){
                    $this->error[]=array(
                        'path'=>$path,
                        'url'=>$url,
                        'is_url'=>$is_url
                    );
                    return false;
                }
                if(!$this->putFile($path,$file)){
                    $this->error[]=array(
                        'path'=>$path,
                        'url'=>$url,
                        'is_url'=>$is_url
                    );
                    return false;
                }
            }else{//不是链接
                //保存文件
                if(!$this->putFile($path,$url)){
                    $this->error[]=array(
                        'path'=>$path,
                        'url'=>$url,
                        'is_url'=>$is_url
                    );
                    return false;
                }
            }
            return true;
        }
        protected function getNeedBetween($kw1,$mark1,$mark2){//在string中找到指定的string
            $kw=$kw1;
            $kw='123'.$kw.'123';
            $st =stripos($kw,$mark1);
            $ed =stripos($kw,$mark2);
            if(($st==false||$ed==false)||$st>=$ed)
                return 0;
            $kw=substr($kw,($st+1),($ed-$st-1));
            return $kw;
        }
        protected function createDir($path){//创建目录
            $path = iconv('utf-8', 'gbk', $path);
            if(!is_dir($path)){
                mkdir($path,0777,true);
            }
        }
	}

	set_time_limit(0); 
	ignore_user_abort(true); 
	$obj = new caiJi('http://singerdream.com/s/tutorial/5980/0','./歌者盟vip视频');
	$obj->run();

