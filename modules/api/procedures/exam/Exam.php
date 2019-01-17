<?php

namespace common\modules\api\procedures\exam;

use Anodoc\Exception;
use common\components\Request;
use common\models\ElExOrder;
use common\models\ElExQuestion;
use common\models\ShopMember;
use common\modules\api\procedures\BaseAppApi;
use common\modules\api\procedures\ApiException;
use common\modules\course\models\ExExam;
use common\modules\course\models\ExExamRecode;
use common\modules\course\models\ExPaper;
use common\modules\course\models\ExPaperContent;

class Exam extends BaseAppApi
{
    /**
     * 知识点测评题目获取接口
     *
     * @category 课程模块
     * @param string $openid
     * @param int $category_id
     * @return array
     * @throws \Exception
     */
    public function get_exam_questions($openid, $category_id) {

        if (!$openid || !is_string($openid)) {
            throw new \Exception('参数错误！');
        }

        if (empty($category_id) || !is_numeric($category_id)) {
            \Yii::warning('获取测试题目时发生错误:找不到分类，分类Id：'.$category_id);
            throw new \Exception('请选择分类！');
        }

        $uniacid = \common\components\Request::getInstance()->uniacid;
        $user = ShopMember::getModel($openid);
        if (!$user) {
            throw new \Exception('未授权!');
        }

        $uid = $user->id;

        $category = \common\modules\course\models\ExQuestionCategory::findOne(['ex_question_category_id' => $category_id]);
        if (!$category) {
            \Yii::warning('请求自动随机出卷时,查无目录,目录id：'.$category_id,__METHOD__);
            throw new \Exception('找不到选择的分类!');
        }

        try {
            $transaction = \Yii::$app->getDb()->beginTransaction();

            $category_name = $category->title;


            //新建试卷
            $paper = new ExPaper();
            $paper->paper_category = $category_id;
            $paper->paper_name = '课程测试卷子';
            $paper->paper_describe = '课程测试卷子';
            $paper->paper_type = 0;
            $paper->paper_status = 1;
            $paper->paper_class = 1;
            $paper->save(false);
            $paper_id = $paper->getAttribute('paper_id');

            //新建考试
            $exam = new ExExam();
            $exam->exam_name = $category_name . '课堂测试';
            $exam->exam_describe = '系统自动随机出卷';
            $exam->exam_categoryid = 0;
            $exam->paper_id = $paper_id;
            $exam->exam_begin_time = time();
            $exam->exam_end_time = time();
            $exam->exam_user_signup_time = time();
            $exam->exam_admin = 0;
            $exam->uniacid = $uniacid;
            $exam->exam_insert_date = time();
            $exam->exam_update_date = time();
            $exam->school_id = 0;
            $exam->exam_admin = 0;
            $exam->exam_type = 1;
            $result = $exam->save(false);

            if ($result) {
                $exam_id = $exam->getAttribute('exam_id');
            } else {
                throw new \Exception('自动随机出卷时发生错误:保存考试失败');
            }

            $question_type_result = \common\modules\course\models\ExQuestionType::find()
                ->select([
                    'el_ex_question_type.question_type_id as type_id',
                    'count(*) as count',
                ])
                ->joinWith('questions', false)
                ->groupBy('el_ex_question_type.question_type_title')
                ->asArray()
                ->all();

            $return = [];
            $question_type = [];
            $q_id = [];
            $right_options = [];
            $right_answer = [];
            $question_count = 0;
            $question_score = 0;

            //读取考试试卷配置
            $config_str = null;
            $ex_config = \common\modules\course\models\ExPaperConfig::fetchOne(['uniacid' => $uniacid]);
            $c_arr = json_decode($ex_config, true);
            if ($c_arr) {
                $config_str = $c_arr['paper_config'];
            }

            if (!empty($config_str) && is_string($config_str)) {
                $q_type = explode(',', $config_str);
                foreach ($q_type as &$value) {
                    $val = explode('-', $value);
                    $type = $val[0];
                    $count = $val[1];
                    $question_type[$type] = $count;
                }
            } else {
                //做默认配置
                foreach ($question_type_result as $value) {
                    $type = $value['type_id'];
                    $count = intval($value['count']);
                    if ($count >= 5) {
                        $question_type[$type] = 5;
                    } else {
                        $question_type[$type] = $count;
                    }
                }
            }

            foreach ($question_type as $key => $value) {
                $question_list = \common\modules\course\models\ExQuestion::fetchAll([['like', 'fullcategorypath', $category_id], 'question_type' => $key, 'question_status' => 1, 'question_is_del' => 0 ]);
                $q_count = count($question_type);
                if ($q_count < $value) {
                    foreach ($question_list as $k => $v) {
                        $content = new ExPaperContent();
                        $content->paper_content_paperid = $paper_id;
                        $content->paper_content_questionid = $v["question_id"];
                        $content->paper_content_point = $v["question_point"];
                        $content->paper_content_item = $k + 1;
                        $content->paper_content_admin = $uniacid;
                        $content->paper_content_update_date = time();
                        $content->paper_content_insert_date = time();
                        $rs = $content->save(false);
                        if ($rs) {
                            $question_count++;
                            $question_score += $question_list[$k]["question_point"];
                            $q_id[] = $v["question_id"];
                        }
                    }
                } else {
                    if ($q_count == 1) {
                        $content = new ExPaperContent();
                        $content->paper_content_paperid = $paper_id;
                        $content->paper_content_questionid = $question_list[0]["question_id"];
                        $content->paper_content_point = $question_list[0]["question_point"];
                        $content->paper_content_item = 1;
                        $content->paper_content_admin = $uniacid;
                        $content->paper_content_update_date = time();
                        $content->paper_content_insert_date = time();
                        $rs = $content->save(false);
                        if ($rs) {
                            $question_count++;
                            $question_score += $question_list[0]["question_point"];
                            $q_id[] = $question_list[0]["question_id"];
                        }
                    } else {
                        $question_id = [];
                        foreach ($question_list as $v) {
                            $question_id[] = $v["question_id"];
                        }
                        $random_array = array_rand($question_id, $q_count);
                        foreach ($random_array as $r => $random) {
                            $content = new ExPaperContent();
                            $content->paper_content_paperid = $paper_id;
                            $content->paper_content_questionid = $question_list[$random]["question_id"];
                            $content->paper_content_point = $question_list[$random]["question_point"];
                            $content->paper_content_item = $r + 1;
                            $content->paper_content_admin = $uniacid;
                            $content->paper_content_update_date = time();
                            $content->paper_content_insert_date = time();
                            $rs = $content->save(false);
                            if ($rs) {
                                $question_count++;
                                $question_score += $question_list[$random]["question_point"];
                                $q_id[] = $question_list[$random]["question_id"];
                            }
                        }
                    }
                }
            }

            $op_chars = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

            $questions = \common\modules\course\models\ExQuestion::fetchAll([['in', 'question_id', $q_id]]);
            if (!empty($questions) && is_array($questions)) {
                foreach ($questions as $question) {
                    $r_opn = [];
                    $q = [];
                    $op = [];
                    $question_id = $question['question_id'];
                    $point = $question['question_point'];
                    $q['content'] = $question['question_content'];
                    $q['point'] = $point;
                    $q['time'] = $question['question_time'];
                    $q['question_type'] = $question['question_type'];
                    $q['question_id'] = $question['question_id'];



                    $options = \common\modules\course\models\ExOption::fetchAll(['option_question' => $question_id]);
                    if ($options) {
                        $i = 0;
                        foreach ($options as $option) {
                            $o = [];
                            $o['option'] = $op_chars[$i];
                            $o['option_content'] = $option['option_content'];
                            $op[] = $o;
                            $q['options'] = $op;

                            if ($option['is_right'] == 1) {
                                $r_opn[] = $op_chars[$i];
                            }
                            $i++;
                        }
                    } else {
                        \Yii::warning('查询题目选项时，查不到相应选项，考试id：'.$exam_id.' 请求题目目录:'.$category_name);
//                        throw new \Exception('系统错误, 题目选项不存在');
                        continue;
                    }

                    $r_answer = [];
                    $r_answer['right_options'] = $r_opn;
                    $r_answer['point'] = $point;
                    $r_answer['question_id'] = $question_id;
                    $right_answer[] = $r_answer;
//                    $right_opn_str = join(',',$r_opn);
//                    $right_opn_str = $question_id.'-'.$right_opn_str;
//                    $right_opn_str = $right_opn_str.':'.$point;
//                    $right_options[] = $right_opn_str;
                    $return[] = $q;

                }
            }

            if (empty($return)) {
                throw new \Exception('没有找到题目喔');
            }

            $paper->paper_point = $question_score;
            $paper->paper_question_count = $question_count;
            $paper->save(false);

            $now_time = time();
            $record_data = [
                'uid' => $uid,
                'exam_id' => $exam_id,
                'ctime' => $now_time,
                'uniacid' => $uniacid
            ];

            \common\modules\course\models\ExExamRecode::insertOne($record_data);


            $transaction->commit();

        } catch (\Exception $e) {
            $transaction->rollBack();
            \Yii::warning('自动出卷请求遇到错误，原因为:'.$e->getMessage(),__METHOD__);
            throw new \Exception($e->getMessage());
        }

        $ex_config = \common\modules\course\models\ExPaperConfig::findOne(['uniacid' =>$uniacid]);
        if ($ex_config) {
            $config = $ex_config->config;
            $config = json_decode($config, true);
            $time = $config['time'];
        }
        if($time)
        {
            $total_time = $time * 60;
        }
        else
        {
            $total_time = 3 * 60;
        }


        foreach ($return as &$question)
        {
            $question['content'] = str_replace('/data/upload','http://'.$_SERVER['SERVER_NAME'].'/data/upload',$question['content']);
        }
        return [
            'exam_id' => $exam_id,
            'paper_id' => $paper_id,
            'total_time' => $total_time,
            'begin_time' => $now_time,
            'questions' => $return,
            'r_answer' => $right_answer,
            'user' => [
                'avatar'   => $user->avatar,
                'nickname' => $user->nickname,
            ]
        ];
    }

    /**
     * 获取百人赛题目信息（百人赛答题请求）
     *
     * @param string $openid 登录token
     * @param integer $exam_id 考试id
     * @return array
     * @throws \Exception
     * @category 课程模块
     * @resultDemo {
     *  "exam_id": "46",
     *  "paper_id": 19,
     *  "begin_time": 1533796993,
     *  "questions": [
     *      {
     *          "content": "经济成分、利益主体、社会组织和社会生活方式日趋多样化、指导思想也可以“多元化”",
     *          "point": 2,
     *          "time": 120,
     *          "has_options": 1,
     *          "options": {
     *              "A": "T",
     *              "B": "F"
     *          }
     *      }
     *  ],
     *  "r_answer": "39-B:2;46-暂无解析!:20;45-B:4;18-D:5;19-A:5;20-A:5;21-D:5;22-D:5;23-B,C,D:10;24-B,C,D:10"
     * }
     * @resultKey int exam_id 考试id（后续提交答案需要回传给服务端）
     * @resultKey int paper_id 试卷id（后续提交答案需要回传给服务端）
     * @resultKey mixed r_answer 正确答案字符串（后续提交答案需要回传给服务端）
     * @resultKey int begin_time 考试开始时间(后续提交答案需要回传给服务端）
     * @resultKey string questions[].content 题目题干
     * @resultKey int questions[].point 题目得分
     * @resultKey int questions[].time 题目答题时间限制（分钟）
     * @resultKey int questions[].question_type 题目类型（1单选2多选3填空4判断）
     * @resultKey array questions[].options 题目选项
     */
    public function get_hundred_competition_questions($openid, $exam_id) {
        if (!$openid || !is_string($openid)) {
            throw new \Exception('参数错误！');
        }

        if (!$exam_id || !is_numeric($exam_id)) {
            throw new \Exception('参数错误！');
        }

        $uniacid = \common\components\Request::getInstance()->uniacid;
        $user = ShopMember::getModel($openid);
        if (empty($user)) {
            \Yii::warning('找不到openid:'.$openid, __METHOD__);
            throw new \Exception('参数错误');
        }

        $uid = $user->id;

        $ex_exam = \common\modules\course\models\ExExam::findOne([
            'exam_id' => $exam_id,
            'exam_status' => 1,
            'exam_is_del' => 0,
            'uniacid' => $uniacid
        ]);

        if(!$ex_exam) {
            \Yii::warning('用户'.$openid.'尝试参加百人赛时，被拒绝,原因是:考试不存在，考试id：'.$exam_id);
            throw new \Exception('id为'.$exam_id.'的考试不存在');
        }

        $user_exam = \common\modules\course\models\ExUserExam::fetchAll([
            'user_exam' => $exam_id,
            'user_id' => $uid,
            'uniacid' => $uniacid
        ]);

        /*
        if (!empty($user_exam)) {
            \Yii::warning('用户'.$openid.'尝试参加百人赛时，被拒绝,原因是:重复参加，考试id：'.$exam_id);
            throw new \Exception('你已经参加过此百人赛了哦');
        }
        */

        $ex_paper = $ex_exam->paper;
        if(!$ex_paper || $ex_paper->paper_status != 1 || $ex_paper->paper_is_del == 1) {
            \Yii::warning('用户'.$openid.'尝试参加百人赛时，被拒绝,原因是:试卷不存在，考试id：'.$ex_exam->exam_id);
            throw new \Exception('id为'.$ex_exam->paper_id.'的试卷不存在');
        }


        $rs = \common\modules\course\models\ExExam::updateAllCounters(['exam_num_now' => 1], '`exam_id` = :exam_id and `exam_num_now` < `exam_num_max` and `uniacid` = :uniacid ', [
            ':exam_id' => $exam_id,
            ':uniacid' => $uniacid
        ]);

        if(!$rs) {
            \Yii::warning('用户'.$openid.'尝试参加百人赛时，被拒绝,原因是:人数已满，考试id：'.$ex_exam->exam_id, __METHOD__);
            throw new \Exception('本场百人赛人数已满！');
        }

        $paper_id = $ex_paper->paper_id;
        $ex_paper_contents = \common\modules\course\models\ExPaperContent::find()->where(['paper_content_paperid' => $paper_id])->orderBy('paper_content_questionid asc')->with('question')->all();
        $questions = [];
        $right_options = [];
        $right_answer = [];
        $op_chars = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

        foreach ($ex_paper_contents as $content) {
            $content_question = $content->question;
            if($content_question) {
                $question = $content_question->getAttributes();

                if ($question['question_status'] == 1 && $question['question_is_del'] == 0) {
                    $r_opn = [];
                    $q = [];
                    $op = [];
                    $q_id = intval($question['question_id']);
                    $point = intval($question['question_point']);
                    $q['content'] = $question['question_content'];
                    $q['point'] = $point;
                    $q['time'] = intval($question['question_time']);
                    $q['question_type'] = intval($question['question_type']);
                    $q['question_id'] = intval($question['question_id']);


                    $options = \common\modules\course\models\ExOption::findAll(['option_question' => $question['question_id']]);
                    if ($options) {
                        $i = 0;
                        foreach ($options as $option) {
                            $option = $option->getAttributes();
                            $o = [];
                            $o['option'] = $op_chars[$i];
                            $o['option_content'] = $option['option_content'];
                            if ($option['is_right'] == 1) {
                                $r_opn[] = $op_chars[$i];
                            }
                            $op[] = $o;
                            $i++;
                        }
                    } else {
                        \Yii::warning('查询题目选项时，查不到相应选项，考试id：' . $ex_exam->exam_id);
//                    throw new \Exception('系统错误, 题目选项不存在');
                    }
                    $r_answer = [];
                    $r_answer['right_options'] = $r_opn;
                    $r_answer['point'] = $point;
                    $r_answer['question_id'] = $q_id;
                    $right_answer[] = $r_answer;

                    $q['options'] = $op;
                    $questions[] = $q;
                }
            }
        }
        $now_time = time();
        $record_data = [
            'uid' => $uid,
            'exam_id' => $exam_id,
            'ctime' => $now_time,
            'uniacid' => $uniacid
        ];

        \common\modules\course\models\ExExamRecode::insertOne($record_data);


        if($ex_exam->exam_total_time_2)
        {
            $total_time = intval($ex_exam->exam_total_time_2) * 60;
        }
        else {
            $ex_config = \common\modules\course\models\ExPaperConfig::findOne(['uniacid' => $uniacid]);
            if ($ex_config) {
                $config = $ex_config->config;
                $config = json_decode($config, true);
                $time = $config['time2'];
            }
            if ($time) {
                $total_time = $time * 60;
            } else {
                $total_time = 3 * 60;
            }
        }
        foreach ($questions as &$question)
        {
            $question['content'] = str_replace('/data/upload','http://'.$_SERVER['SERVER_NAME'].'/data/upload',$question['content']);
        }

        return [
            'exam_id' => $exam_id,
            'paper_id' => $paper_id,
            'total_time' => $total_time,
            'begin_time' => $now_time,
            'questions' => $questions,
            'r_answer' => $right_answer,
            'user' => [
                'avatar'   => $user->avatar,
                'nickname' =>$user->nickname
            ]
        ];
    }

    /**
     * 购买指定题目，获取支付数据
     *
     * @category 课程模块
     * @param string $openid
     * @param int $questionid
     * @return array
     * @throws Exception
     */
    public function do_pay_answer($openid,$questionid)
    {
        $question_id = intval($questionid);

        $question = ElExQuestion::findOne(
               array('question_id'=>$question_id)
        );
        if(!$question)
        {
            throw new Exception('题目不存在');
        }

        //获取用户信息
        $user = ShopMember::getModel($openid);

        if (!$user) {
            throw new \Exception('openid参数错误!');
        }

        $user = ShopMember::getModel($openid);

        if(!$user->mobileverify)
        {
            throw new \Exception('未绑定手机，无法查看解析，请到个人中心绑定！');
        }

        //查询是否支付过的订单，返回状态
        $order_result = ElExOrder::findOne(
            array('uniacid'=>Request::getInstance()->uniacid,'user_id'=>$user->id,'pay_status'=>1,'question_id'=>intval($questionid))
        );
        if($order_result||doubleval($question['question_price']) == 0.00)
        {
            $result = array(
                'appId'=>'',
                'timeStamp' =>'',
                'nonceStr'=> '',
                'package'=> 'prepay_id=',
                'signType'=> 'MD5',
                'paySign'=> '7D5A9FB754EB165A6D85385303694C10',
                'rsn'=> 'f5c0385560f0bde0c1c286110262a403',
                'pay_status'=> 1
            );
            return $result;
        }
        else
        {
            $set = \common\models\ShopSysSet::getByKey(array('shop', 'pay'));
            if(empty($set['pay']['wxapp']))
            {
                throw new Exception('未开启微信支付');
            }
            //下单
            $order_sn = date('YmdHis') . rand(10000000,99999999);
            $order = new ElExOrder();
            $order->uniacid = Request::getInstance()->uniacid;
            $order->create_time = time();
            $order->order_sn = $order_sn;
            $order->order_status = 1;
            $order->user_id = $user->id;
            $order->pay_status = 0;
            $order->price = $question['question_price'];
            $order->question_id = $question_id;
            $order->save(false);

            $log = [
                    'uniacid' => Request::getInstance()->uniacid,
                    'openid' => $user->openid_wa,
                    'module' => 'eduline',
                    'tid' => $order_sn,
                    'fee' => $order->price,
                    'status' => 0,
            ];
            \common\models\CorePayLog::insertOne($log);


            $payinfo = [
                'openid' => $user->openid_wa,
                'title' =>  '查看答题解析'. '订单',
                'tid' => $order_sn,
                'fee' => $order->price,
            ];
            $res = \common\modules\wxapp\Module::getWxappPayData($payinfo, 20);
            $res['pay_status'] = 0;
            return $res;


        }
        //下单唤起支付
        //返回
       
    }

    /**
     * 获取交卷后答题结果接口
     *
     * @category 课程模块
     * @param $openid
     * @param $exam_id
     * @param $paper_id
     * @param $begin_time
     * @param $answer
     * @param $r_answer
     * @param $r_answer
     * @return array
     * @throws \Exception
     */
    public function do_submit_exam($openid, $exam_id, $paper_id, $begin_time, $answer, $r_answer,$form_id = null) {
        if (empty($openid) || empty($exam_id) || empty($paper_id) || empty($begin_time) || empty($answer) || empty($r_answer)) {
            \Yii::warning('类型为空参数错误',__METHOD__);
            throw new \Exception('参数错误');
        }
        //普通交卷需要检查考试和试卷id
        if (!is_numeric($exam_id) || !is_numeric($paper_id) || !is_string($openid)) {
            \Yii::warning('检查类型参数错误',__METHOD__);
            throw new \Exception('参数错误');
        }


        $uniacid = \common\components\Request::getInstance()->uniacid;
        $user = ShopMember::fetchOne(['openid' => $openid, 'uniacid' => $uniacid],['id', 'avatar', 'nickname']);
        if (!$user || !is_array($user)) {
            throw new \Exception('openid参数错误!');
        }
        $uid = $user['id'];

        $exam = \common\modules\course\models\ExExam::findOne([
            'uniacid' => $uniacid,
            'paper_id' => $paper_id,
            'exam_id' => $exam_id,
        ]);

        if (!$exam) {
            \Yii::warning('尝试交卷失败，原因是找不到对应试卷的考试, 考试Id：' . $exam_id . ', 试卷id：' . $paper_id, __METHOD__);
            throw new \Exception('考试不合法');
        }

        $exam_type = $exam->exam_type;

        //防止利用技术手段重新提交百人赛答案
        $condition = [
            'user_id' => $uid,
            'user_exam' => $exam_id,
            'user_paper' => $paper_id,
            'el_ex_user_exam.uniacid' => $uniacid
        ];

        $user_exam = \common\modules\course\models\ExUserExam::fetchOne($condition);


//        if (!empty($user_exam)) {
//            \Yii::warning('用户'.$openid.', uid:'.$uid.'尝试重复提交百人赛答案，exam_id：'.$exam_id.',paper_id：'.$paper_id, __METHOD__);
//            throw new \Exception('不能重复提交百人赛');
//        }



        $score = 0;
        $right_num = 0;
        $error_num = 0;
        $result = [];
        $time_str = '';
        $now_time = time();
        $bg_time = $begin_time;
        $d_time = $now_time - intval($bg_time);
        $point_list = [];
        $q_answer_list = [];
        $right_answer = [];

        $answer_arr = $answer;
        $r_answer_arr = $r_answer;

        if (!is_array($answer_arr) || !is_array($r_answer_arr)) {
            throw new \Exception('参数错误');
        }


        //处理r_answer
        foreach ($r_answer_arr as $value) {
            $question_id = $value['question_id'];
            $right_answer[$question_id]['right_options'] = $value['right_options'];
            $right_answer[$question_id]['point'] = $value['point'];
        }

        //计算答题时间
        if ($d_time > 0) {
            $hour = floor($d_time / 3600);
            $minute = floor( ( $d_time - $hour * 3600 ) / 60);
            $second = $d_time % 60;

            $time_str .= $hour > 0 ? $hour . ':' : '';
            $time_str .= $minute > 0 ? $minute . ':' : '';
            $time_str .= $second;

        } else {
            $time_str = '00:00:00';
        }


        //计算得分
        $user_answer_data = [
            'user_id' => $uid,
            'user_exam_id' => $exam_id,
            'user_paper_id' => $paper_id,
            'uniacid' => $uniacid,
        ];
        foreach ($answer_arr as $key => $value) {
            $is_right = 1;
            $question_id = $key;
            $point = $right_answer[$key]['point'];
            $r_a_val = join(',', $value);
            $point = is_numeric($point) ? $point : 0;
            if (is_array($answer_arr[$key]) && is_array($right_answer[$key]['right_options'])) {
                $right_options = $right_answer[$key]['right_options'];
                $options = $answer_arr[$key];
                if(empty($options))
                {
                    $is_right = 0;
                    $point = 0;
                }
                foreach ($options as $k => $v) {
                    if (!in_array($v, $right_options)) {
                        $is_right = 0;
                        $point = 0;    //答错该题没分
                        break;
                    }
                }
            } else {
                $is_right = 0;
                $point = 0;
            }


            $q_answer_list[$question_id] = empty($r_a_val) ? '未填' : $r_a_val;
            $point_list[$question_id] = $point;
            $result[] = $is_right;

            if ($is_right == 1) {
                $score += $point;
                $right_num++;
            } else {
                $error_num++;
            }
        }
        foreach ($q_answer_list as $key => $val) {
            $user_answer_data['user_question_id'] = $key;
            $user_answer_data['user_question_answer'] = $q_answer_list[$key];
            $user_answer_data['user_score'] = $point_list[$key];
            \common\modules\course\models\ExUserAnswer::insertOne($user_answer_data);
        }
        //计算正确率
        $total_num = ($right_num + $error_num);
        $right_rate = floor(($right_num / $total_num) * 100);
        $right_rate_str = $right_rate . '%';

        //查看合格分数线
//        $passing_grade = \common\modules\course\models\ExExam::fetchOne(['exam_id' => $exam_id], ['exam_passing_grade']);
//        $passing_score = intval(empty($passing_grade) ? 0 : $passing_grade['exam_passing_grade'] );
//        $is_pass = ( $score >= $passing_score ) ? 1 : 0;

        $exam_result_data = [
            'user_id' => $uid,
            'user_exam' => $exam_id,
            'user_paper' => $paper_id,
            'user_exam_time' => $now_time,
            'user_exam_score' => $score,
            'user_right_count' => $right_num,
            'user_error_count' => $error_num,
            'user_total_date' => $time_str,
            'uniacid' => $uniacid,
        ];



        $rs = \common\modules\course\models\ExUserExam::insertOne($exam_result_data, $condition);

        //答对题目增加用户威望
        if ($right_num) {
            $inc_prestige = 1; //先默认每对一题加1分
            $credit = 1;

            $ex_config = \common\modules\course\models\ExPaperConfig::findOne(['uniacid' => $uniacid]);
            if ($ex_config) {
                $config = json_decode($ex_config->config, true);
                if ($config && is_array($config)) {
                    $inc_prestige = $config['inc_prestige'];
                    $credit = $config['credit'];
                }
            }
            $inc_prestige *= $right_num;
            $credit *= $right_num;

            \Yii::warning('会员' . $openid . '答对' . $right_num . '道题，增加会员' . $inc_prestige . '个威望值');
            m('member')->setCredit($openid, 'credit3', $inc_prestige, array(0, '增加会员' . $inc_prestige . '威望值'));
            m('member')->setCredit($openid, 'credit1', $credit, array(0, '增加会员' . $credit . '积分'));


        }

        $return = [
            'score' => $score,
            'result' => $result,
            'right_num' => $right_num,
            'last_time' => $time_str,
            'right_rate_str' => $right_rate_str,
            'right_rate' => $right_rate,
            'user' => $user,
        ];

        if($rs) {

            $f_id = [];
            $friends = \common\modules\course\models\ExUserRelationship::find()
                ->where(['and', ['or',['invite_uid' => $uid], ['invited_uid' => $uid]], ['uniacid' => $uniacid]])
                ->select([
                    'invited_uid',
                    'invite_uid',
                ])
                ->asArray()
                ->all();

            foreach ($friends as $friend) {
                $f_id[] = $friend['invited_uid'];
                $f_id[] = $friend['invite_uid'];
            }

            $f_id = array_unique($f_id);
            $condition[] = ['in', 'user_id', $f_id];

            if($exam_type == 1) {
                $condition = [
                    'and',
                    ['el_ex_exam.exam_type' => 1],
                    ['el_ex_exam.uniacid' => $uniacid],
                    ['user_id'=> $f_id]
                ];


                $hc_list = \common\modules\course\models\ExUserAnswer::find()
                    ->where($condition)
                    ->select([
                        'ims_new_shop_member.id',
                        'sum(`el_ex_user_answer`.`user_score`) as score',
                    ])
                    ->joinWith(['member', 'exam', 'question'], false)
                    ->orderBy('score desc')
                    ->groupBy(['uid'])
                    ->limit(1000)
                    ->asArray()
                    ->all();
            } else {
                $condition = [
                    'and',
                    ['!=', 'user_score', 0],
                    ['el_ex_exam.exam_type' => 0],
                    ['el_ex_exam.uniacid'   => $uniacid],
                    ['user_id'=> $f_id]
                ];

                $hc_list = \common\modules\course\models\ExUserAnswer::find()
                    ->where($condition)
                    ->select([
                        'count(*) as right_num',
                        'ims_new_shop_member.id',
                    ])
                    ->joinWith(['member', 'exam'],false)
                    ->orderBy('right_num desc, id asc')
                    ->groupBy(['id'])
                    ->limit(1000)
                    ->asArray()
                    ->all();

            }

            $rank = 1;
            foreach ($hc_list as $index => $value) {
                if ($uid == $value['id']) {
                    $rank = $index+1;
                    break;
                }
            }

            $return['friend_rank'] = $rank;


            if($exam_type == 1) {
                $condition = [
                    'and',
                    ['el_ex_exam.exam_type' => 1],
                    ['el_ex_exam.uniacid' => $uniacid]
                ];


                $hc_list_2 = \common\modules\course\models\ExUserAnswer::find()
                    ->where($condition)
                    ->select([
                        'ims_new_shop_member.id',
                        'sum(`el_ex_user_answer`.`user_score`) as score',
                    ])
                    ->joinWith(['member', 'exam', 'question'], false)
                    ->orderBy('score desc')
                    ->groupBy(['uid'])
                    ->limit(1000)
                    ->asArray()
                    ->all();
            } else {
                $condition = [
                    'and',
                    ['!=', 'user_score', 0],
                    ['el_ex_exam.exam_type' => 0],
                    ['el_ex_exam.uniacid'   => $uniacid]
                ];

                $hc_list_2 = \common\modules\course\models\ExUserAnswer::find()
                    ->where($condition)
                    ->select([
                        'count(*) as right_num',
                        'ims_new_shop_member.id',
                    ])
                    ->joinWith(['member', 'exam'],false)
                    ->orderBy('right_num desc, id asc')
                    ->groupBy(['id'])
                    ->limit(1000)
                    ->asArray()
                    ->all();

            }

            $rank2 = 1;
            foreach ($hc_list_2 as $index => $value) {
                if ($uid == $value['id']) {
                    $rank2 = $index+1;
                    break;
                }
            }

            $return['rank'] = $rank2;

        }

        $accessToken = \common\modules\wxapp\Module::getAccessToken();
        $user = ShopMember::fetchOne(['openid' => $openid, 'uniacid' => $uniacid],['id', 'credit3', 'nickname']);
        $openid = \common\Helper::formatOpenID($openid);
        $data_arr = array(
            'keyword1' => array("value" => $user['nickname'],"color"=>"#223c8b"),
            'keyword2' => array("value" => $exam['exam_name'],"color"=>"#223c8b"),
            'keyword3' => array("value" => $rank2,"color"=>"#223c8b"),
            'keyword4' => array("value" => $score,"color"=>"#223c8b"),
            'keyword5' => array("value" => intval($user['credit3']),"color"=>"#223c8b"),
        );
        $open_id = $openid;
        $form_id = $form_id;
        $template_id ="JSyG9k_LNghSmfaOHwyw2Ke9h6ZPzJgQHJCavxP3qsE";
        $page = "pages/index/index";
        $xcx_result = $this->sendTemplateMsg($accessToken,$open_id,$form_id,$template_id,$page,$data_arr);
        \Yii::info('小程序推送'.'openid:'.$openid.',form_id'.$form_id.','.json_encode($xcx_result));

        return $return;

    }

    /**
     * 提交错题集答案？？
     *
     * @category 课程模块
     * @param $openid
     * @param $answer
     * @param $r_answer
     * @param $begin_time
     * @param null $form_id
     * @return array
     * @throws \Exception
     */
    public function do_submit_error_question_answer($openid, $answer, $r_answer, $begin_time,$form_id = null) {
        if (empty($openid) || empty($begin_time) || empty($answer) || empty($r_answer)) {
            throw new \Exception('参数错误');
        }
        //普通交卷需要检查考试和试卷id
        if (!is_string($openid)) {
            throw new \Exception('参数错误');
        }

        $uniacid = \common\components\Request::getInstance()->uniacid;
        $user = ShopMember::fetchOne(['openid' => $openid, 'uniacid' => $uniacid],['id', 'avatar', 'nickname']);
        if (!$user || !is_array($user)) {
            throw new \Exception('openid参数错误!');
        }

        $score = 0;
        $right_num = 0;
        $error_num = 0;
        $result = [];
        $time_str = '';
        $now_time = time();
        $bg_time = $begin_time;
        $d_time = $now_time - intval($bg_time);
        $right_answer = [];


        $answer_arr = $answer;
        $r_answer_arr = $r_answer;

        if (!is_array($answer_arr) || !is_array($r_answer_arr)) {
            throw new \Exception('参数错误');
        }


        //处理r_answer
        foreach ($r_answer_arr as $value) {
            $question_id = $value['question_id'];
            $right_answer[$question_id]['right_options'] = $value['right_options'];
            $right_answer[$question_id]['point'] = $value['point'];
        }

        //计算答题时间
        if ($d_time > 0) {
            $hour = floor($d_time / 3600);
            $minute = floor( ( $d_time - $hour * 3600 ) / 60);
            $second = $d_time % 60;

            $time_str .= $hour > 0 ? $hour.':' : '';
            $time_str .= $minute > 0 ? $minute.':' : '';
            $time_str .= $second;

        } else {
            $time_str = '00:00:00';
        }


        foreach ($answer_arr as $key => $value) {
            $is_right = 1;
            $question_id = $key;
            $point = $right_answer[$key]['point'];
            $r_a_val = join(',', $value);
            $point = is_numeric($point) ? $point : 0;
            if (is_array($answer_arr[$key]) && is_array($right_answer[$key]['right_options'])) {
                $right_options = $right_answer[$key]['right_options'];
                $options = $answer_arr[$key];
                foreach ($options as $k => $v) {
                    if (!in_array($v, $right_options)) {
                        $is_right = 0;
                        $point = 0;    //答错该题没分
                    }
                }
            } else {
                $point = 0;
            }


            $q_answer_list[$key] = empty($r_a_val) ? '未填' : $r_a_val;
            $point_list[$key] = $point;

            $result[] = $is_right;

            if ($is_right == 1) {
                $score += $point;
                $right_num++;
            } else {
                $error_num++;
            }
        }
        //计算正确率
        $right_rate = floor(( $right_num / ($right_num + $error_num) ) * 100 );
        $right_rate_str = $right_rate.'%';

        return [
            'score' => $score,
            'result' => $result,
            'right_num' => $right_num,
            'last_time' => $time_str,
            'right_rate_str' => $right_rate_str,
            'right_rate' => $right_rate,
            'user' => $user->toArray(),
        ];
    }

    /**
     * 课程-发送模板消息
     *
     * @category 课程模块
     * @param $token
     * @param $open_id
     * @param $form_id
     * @param $template_id
     * @param $page
     * @param $data_arr
     * @return bool|string
     */
    public function sendTemplateMsg($token,$open_id,$form_id,$template_id,$page,$data_arr)
    {
        /*
        $data_arr = array(
            'keyword1' => array( "value" => $value, "color" => $color )
            //这里根据你的模板对应的关键字建立数组，color 属性是可选项目，用来改变对应字段的颜色
        );
        */
        $post_data = array (
            "touser"           => $open_id,
            //用户的 openID，可用过 wx.getUserInfo 获取
            "template_id"      => $template_id,
            //小程序后台申请到的模板编号
            "page"             => $page,//"/pages/check/result?orderID=".$orderID,
            //点击模板消息后跳转到的页面，可以传递参数
            "form_id"          => $form_id,
            //第一步里获取到的 formID
            "data"             => $data_arr
            //需要强调的关键字，会加大居中显示
        );


        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=".$token;
        //这里替换为你的 appID 和 appSecret
        $data = json_encode($post_data, true);
        //将数组编码为 JSON
        $return = $this->send_post( $url, $data);

        return $return;
    }

    protected function send_post( $url, $post_data ) {
        $options = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => 'Content-type:application/json',
                //header 需要设置为 JSON
                'content' => $post_data,
                'timeout' => 60
                //超时时间
            )
        );

        $context = stream_context_create( $options );
        $result = file_get_contents( $url, false, $context );

        return $result;
    }

}