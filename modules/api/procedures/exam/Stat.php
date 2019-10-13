<?php
namespace common\modules\api\procedures\exam;

use common\components\Request;
use common\Helper;
use common\helpers\Url;
use common\models\MemberLevel;
use common\modules\api\procedures\BaseAppApi;
use common\modules\api\procedures\ApiException;
use common\models\ShopMember;
use common\modules\course\models\ExQuestionCategory;
use common\modules\course\models\ExUserExam;
use yii\helpers\ArrayHelper;

class Stat extends BaseAppApi
{

    /**
     * 获取答题类别记录接口
     *
     * @category 课程模块
     * @param $openid
     * @return array
     * @throws \Exception
     */
    public function get_exam_category_record($openid) {

        if (!$openid || !is_string($openid)) {
            throw new \Exception('参数错误！');
        }

        $uniacid = Request::getInstance()->uniacid;
        $user = ShopMember::getModel($openid);
        if (!$user) {
            throw new \Exception('openid参数错误!');
        }
        $uid = $user->id;

        /** @var ExUserExam[] $exam_records */
        $exam_records = ExUserExam::find()
            ->where(['user_id' => $uid, 'uniacid' => $uniacid])
            ->with('exam')
            ->all();
        if(!$exam_records) {
            throw new \Exception('考试记录不存在');
        }

        $exams = [];
        $return = [];
        $total_right_num = 0;
        $total_num = 0;
        foreach ($exam_records as $exam_record) {
            $exam_time = $exam_record->user_exam_time;
            $time_str = date('Y-m-d h:i', $exam_time);
            $time_arr = explode(' ', $time_str);
            $date    = $time_arr[0];
            $time    = $time_arr[1];
            $date_arr = explode('-', $date);
            $year    =  $date_arr[0];
            $month   =  $date_arr[1];
            $day     =  $date_arr[2];
            $today_date = date('Y-m-d', time());
            $yesterday_date = date('Y-m-d', time() - 86400);

            $is_today = $today_date === $date ? 1 : 0;
            $is_yesterday = $yesterday_date === $date ? 1 : 0;


            $right_num = intval($exam_record->user_right_count);
            $error_num = intval($exam_record->user_error_count);
            $questions_num = $right_num + $error_num;
            $right_rate = floor(( $right_num / $questions_num ) * 100);

            $total_right_num += $right_num;
            $total_num += $questions_num;

            $e = [];
            $ex = [];
            $exam = $exam_record->exam;
            $ex['name'] = $exam->getAttribute('exam_name');
            $ex['exam_id'] = $exam->getAttribute('exam_id');
            $ex['right_rate'] = $right_rate.'%';
            $ex['right_num'] = $right_num;
            $ex['is_today'] = $is_today;
            $ex['is_yesterday'] = $is_yesterday;
            $ex['num']  = $questions_num;
            $ex['date'] = $date;
            $ex['time'] = $time;

            $ex['remark'] = "{$time}共{$questions_num}道";

            $e['exams'] = $ex;
            $e['month'] = $month;
            $e['year']  = $year;
            $exams[$year][$month][]    = $ex;
        }

        foreach ($exams as $year => &$value) {  //每一年
            $groupItem = [
                'list' => [],
            ];
            if($value && is_array($value)) {
                foreach ($value as $month => &$val) {
                    if ($val && is_array($val)) {
                        $groupItem['list'][] = $val;
                    }
                }
                $groupItem['title'] = "{$year}年";
                // TODO
                $groupItem['remark'] = '测试：15道，正确率40%';
                $return[] = $groupItem;
            }
        }
        return ['record_category' => $return, 'total_right' => $total_right_num, 'total_num' => $total_num];
    }

    /**
     * 获取答题测试详细记录接口
     *
     * @category 课程模块
     * @param $openid
     * @param $exam_id
     * @return array
     * @throws \Exception
     */
    public function get_exam_record($openid, $exam_id) {

        if (!$openid || !is_string($openid)) {
            throw new \Exception('参数错误！');
        }

        if (!$exam_id || !is_numeric($exam_id)) {
            throw new \Exception('参数错误！');
        }

        $user = ShopMember::getModel($openid);
        if (!$user) {
            throw new \Exception('openid参数错误!');
        }

        $exam = \common\modules\course\models\ExExam::fetchOne(['exam_id' => $exam_id]);
        if(!$exam) {
            throw new \Exception('考试不存在');
        }

        $paper_id = $exam['paper_id'];
        $ex_paper_contents = \common\modules\course\models\ExPaperContent::find()->where(['paper_content_paperid' => $paper_id])->with('question')->all();

        $question_ids = [];
        foreach ($ex_paper_contents as $content) {
            $content_question = $content->question;
            if($content_question) {
                $question_ids[] = $content_question->getAttribute('question_id');
            }
        }

        //非重复处理
        array_unique($question_ids);

        //联表查询题目和题目对应的选项
        $questions = \common\modules\course\models\ExQuestion::find()->where(['in', 'question_id', $question_ids])->with('options')->all();
        if (!$questions) {
            throw new \Exception('找不到问题');
        }

        //预定义选项字符，有待通过查询数据库优化
        $op_chars = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        $return = [];

        //遍历题目
        foreach ($questions as $question) {
            $options = $question->options;
            $q  = [];
            $op = [];
            $r_opn = [];
            $i  = 0;
            //遍历选项
            foreach ($options as $option) {
                $option = $option->getAttributes();
                $o = [];
                $o['option'] = $op_chars[$i];
                $o['option_content'] = $option['option_content'];
                $op[] = $o;
                //筛选正确答案
                if($option['is_right'] == 1) {
                    $o = [];
                    $r_opn[] = $op_chars[$i];
                }
                $i++;
            }
            $q_id = $q['question_id'] = $question->question_id;
            $point = $question['question_point'];
            $q['right_options'] = $r_opn;
            $q['options'] = $op;
            $q['question_content'] = str_replace('/data/upload',Url::to('/data/upload'),$question['question_content']);
            $q['guide'] = str_replace('/data/upload',Url::to('/data/upload'),$question['question_qsn_guide']);


            $return[] = $q;
        }


        return [
            'questions' => $return,
        ];
    }

    /**
     * 获取错题集子分类接口
     *
     * @category 课程模块
     * @param $openid
     * @return array
     * @throws \Exception
     */
    public function get_error_answer_album_category($openid) {
        if (!$openid || !is_string($openid)) {
            throw new \Exception('参数错误！');
        }

        $user = ShopMember::getModel($openid);
        if (!$user) {
            throw new \Exception('openid参数错误!');
        }
        $uid = $user->id;

        $error_answer_record = \common\modules\course\models\ExUserAnswer::fetchAll(['user_id' => $uid]);
        $error_answer_question_ids = [];
        $error_answer_categorys = [];
        $error_answer_question_id_arr = [];
        $count = [];
        $return = [];
        $status = 0;

        if($error_answer_record) {
            foreach ($error_answer_record as $val) {
                $score = intval($val['user_score']);
                if ($score === 0) {
                    $error_answer_question_ids[] = $val['user_question_id'];
                }
            }

            $error_answer_questions = \common\modules\course\models\ExQuestion::find()->where(['in', 'question_id', $error_answer_question_ids])->with('category')->all();
            foreach ($error_answer_questions as $question) {
                $category = $question->category;
                if($category) {
                    $name = $category->getAttribute('title');
                    $id = strval($category->getAttribute('ex_question_category_id'));
                    $error_answer_categorys[$id] = $name;
                    $error_answer_question_id_arr[$id][] = $question->question_id;
                    $count[$id]++;           //该类错题数量自增
                }
            }

            array_unique($error_answer_categorys);

            foreach ($error_answer_categorys as $key => $val) {
                $c = [];
                $c['category_name'] = $val;
                $c['category_id'] = $key;
                $c['question_id'] = join(',', $error_answer_question_id_arr[strval($key)]);
                $c['count'] = $count[strval($key)];
                $return[] = $c;
            }

        }
        return ['category' => $return];
    }


    /**
     * 获取错题集分类里详细内容接口
     *
     * @category 课程模块
     * @param $openid
     * @param $category_id
     * @return array
     * @throws \Exception
     */
    public function get_error_answer_album_detail($openid, $category_id) {
        //TODO 获取某分类错题集详情
        if (empty($openid) || !is_string($openid)) {
            throw new \Exception('参数错误！');
        }

        if (empty($category_id) || !is_numeric($category_id)) {
            throw new \Exception('category_id参数错误');
        }

        $user = ShopMember::getModel($openid);
        if (!$user) {
            throw new \Exception('openid参数错误!');
        }
        $uid = $user->id;

        //查询当前用户在该分类下的答错的题目
        $query = \common\modules\course\models\ExQuestion::find()
            ->select('el_ex_question.question_id as id')
            ->joinWith('answers', false)
            ->andWhere(['el_ex_user_answer.user_id' => $uid])
            ->andWhere(['el_ex_user_answer.user_score' => 0])
            ->andWhere(['el_ex_question.question_category' => $category_id]);

        $result_ids = $query->asArray()->all();

        //查询结果题目id集合
        $question_ids = [];
        foreach ($result_ids as $val) {
            $question_ids[] = $val['id'];
        }

        //非重复处理
        array_unique($question_ids);

        //联表查询题目和题目对应的选项
        $questions = \common\modules\course\models\ExQuestion::find()->where(['in', 'question_id', $question_ids])->with('options')->all();
        if (!$questions) {
            throw new \Exception('找不到问题');
        }

        //预定义选项字符，有待通过查询数据库优化
        $op_chars = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        $return = [];

        //遍历题目
        foreach ($questions as $question) {
            $options = $question->options;
            $q  = [];
            $op = [];
            $r_opn = [];
            $i  = 0;
            //遍历选项
            foreach ($options as $option) {
                $option = $option->getAttributes();
                $o = [];
                $o['option'] = $op_chars[$i];
                $o['option_content'] = $option['option_content'];
                $op[] = $o;
                //筛选正确答案
                if($option['is_right'] == 1) {
                    $o = [];
                    $r_opn[] = $op_chars[$i];
                }
                $i++;
            }
            $q_id = $q['question_id'] = $question->question_id;
            $point = $question['question_point'];
            $q['right_options'] = $r_opn;
            $q['options'] = $op;

            $q['question_content'] = str_replace('/data/upload',Url::to('/data/upload'),$question['question_content']);
            $q['guide'] = str_replace('/data/upload',Url::to('/data/upload'),$question['question_qsn_guide']);

            $return[] = $q;
        }


        return [
            'questions' => $return,
        ];
    }


    /**
     * 错题重做
     *
     * @category 课程模块
     * @param $openid
     * @param $category_id
     * @return array
     * @throws \Exception
     */
    public function get_redo_error_question($openid, $category_id) {
        if (empty($openid) || !is_string($openid)) {
            throw new \Exception('参数错误！');
        }

        if (empty($category_id) || !is_numeric($category_id)) {
            throw new \Exception('category_id参数错误');
        }

        $uniacid = Request::getInstance()->uniacid;
        $user = ShopMember::getModel($openid);
        if (!$user) {
            throw new \Exception('openid参数错误!');
        }
        $uid = $user->id;

        //查询当前用户在该分类下的答错的题目
        $query = \common\modules\course\models\ExQuestion::find()
            ->select('el_ex_question.question_id as id')
            ->joinWith('answers', false)
            ->andWhere(['el_ex_user_answer.user_id' => $uid])
            ->andWhere(['el_ex_user_answer.user_score' => 0])
            ->andWhere(['el_ex_question.uniacid' => $uniacid])
            ->andWhere(['el_ex_question.question_category' => $category_id]);

        $result_ids = $query->asArray()->all();

        //查询结果题目id集合
        $question_ids = [];
        foreach ($result_ids as $val) {
            $question_ids[] = $val['id'];
        }

        //非重复处理
        array_unique($question_ids);

        //联表查询题目和题目对应的选项
        $questions = \common\modules\course\models\ExQuestion::find()->where(['in', 'question_id', $question_ids])->with('options')->all();
        if (!$questions) {
            throw new \Exception('找不到问题');
        }

        //预定义选项字符，有待通过查询数据库优化
        $op_chars = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        $return = [];
        $right_answer = [];
        //遍历题目
        foreach ($questions as $question) {
            $options = $question->options;
            $q  = [];
            $op = [];
            $r_opn = [];
            $i  = 0;
            //遍历选项
            foreach ($options as $option) {
                $option = $option->getAttributes();
                $o = [];
                $o['option'] = $op_chars[$i];
                $o['option_content'] = $option['option_content'];
                $op[] = $o;
                //筛选正确答案
                if($option['is_right'] == 1) {
                    $o = [];
                    $r_opn[] = $op_chars[$i];
                }
                $i++;
            }
            $q_id = $q['question_id'] = $question->question_id;
            $point = $question['question_point'];
            $q['point'] = $point;
            $q['time'] = intval($question['question_time']);
            $q['question_type'] = intval($question['question_type']);
            $q['right_options'] = $r_opn;
            $q['options'] = $op;

            $q['content'] = str_replace('/data/upload',Url::to('/data/upload'),$question['question_content']);
            $q['guide'] = str_replace('/data/upload',Url::to('/data/upload'),$question['question_qsn_guide']);


            $r_answer = [];
            $r_answer['right_options'] = $r_opn;
            $r_answer['point'] = $point;
            $r_answer['question_id'] = $q_id;
            $right_answer[] = $r_answer;
            $return[] = $q;
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

        return [
            'begin_time' => time(),
            'questions' => $return,
            'total_time' => $total_time,
            'r_answer' => $right_answer,
            'user' => [
                'avatar'   => $user->avatar,
                'nickname' =>$user->nickname
            ]
        ];
    }

    /**
     * 获取百人赛列表接口
     *
     * @category 课程模块
     * @param $openid
     * @return array
     * @throws \Exception
     */
    public function get_hundred_competition_list($openid) {
        //TODO 获取百人赛列表
        if (!$openid || !is_string($openid)) {
            throw new \Exception('参数错误！');
        }

        $uniacid = Request::getInstance()->uniacid;
        $user = ShopMember::getModel($openid);

        if (!$user) {
            throw new \Exception('openid参数错误!');
        }

        $hc_list = \common\modules\course\models\ExExam::fetchAll([
            'exam_type' => 0,
            'exam_status' => 1,
            'exam_is_del' => 0,
            'uniacid' => $uniacid
        ],'exam_id DESC');

        if(!$hc_list) {
            throw new \Exception('查无结果');
        }

        $host_name = \Yii::$app->request->hostInfo;
        $return = [];
        $img_arr = [
            $host_name.'/edu/static/api/img/share/hc_image1.png',
            $host_name.'/edu/static/api/img/share/hc_image2.png',
            $host_name.'/edu/static/api/img/share/hc_image3.png',
            $host_name.'/edu/static/api/img/share/hc_image4.png',
        ];
        $head_image = Url::to('/edu/static/api/img/share/hc_head.png', true);

        foreach ($hc_list as $val) {
            $v = [];
            $v['exam_id']   = $val['exam_id'];
            $v['exam_name'] = $val['exam_name'];
            $v['bg_img']    = $img_arr[mt_rand(0, 3)];  //随机背景图片
            $return[] = $v;
        }



        return ['list' => $return,  'head_img' => $head_image];

    }


    /**
     * 获取百人赛排行榜接口
     *
     * @category 课程模块
     * @param $openid
     * @param $exam_id
     * @param int $type
     * @param int $limit
     * @return array|\yii\db\ActiveRecord[]
     * @throws \Exception
     */
    public function get_hundred_competition_rank($openid, $exam_id, $type = 0, $limit = 4) {
        //TODO 获取百人赛排行榜
        if (!$openid || !is_string($openid)) {
            throw new \Exception('参数错误！');
        }

        $uniacid = Request::getInstance()->uniacid;
        $user = ShopMember::getModel($openid);
        if (!$user) {
            throw new \Exception('openid参数错误!');
        }
        $limit = 20;
        $condition = [
            'and',
            ['!=', 'user_score', 0],
            ['exam_type' => 0],
            ['el_ex_exam.uniacid'   => $uniacid],
        ];

        if (!empty($exam_id)) {
            $condition[] = ['user_exam_id' => $exam_id];
        }

        //判断是否是好友排行榜
        if($type) {
            $f_id = [];
            $uid = $user->id;
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
        }
        $condition[] = ['el_ex_user_answer.uniacid' => $uniacid];

        $hc_list = \common\modules\course\models\ExUserAnswer::find()
            ->where($condition)
            ->select([
                'count(*) as right_num',
                'el_ex_user_answer.user_id',
                'ims_new_shop_member.avatar',
                'ims_new_shop_member.city',
                'ims_new_shop_member.nickname',
                ])
            ->joinWith(['member', 'exam'],false)
            ->orderBy('right_num desc, uid asc')
            ->groupBy(['user_id'])
            ->limit($limit)
            ->asArray()
            ->all();

        foreach ($hc_list as &$item)
        {
            $item['remark'] = '答对题目：'.$item['right_num'];
            if(empty($item['avatar']))
            {
                $item['avatar'] = 'https://xsc.jutouit.com/static/images/avatar.png';
            }
            if(empty($item['city']))
            {
                $item['city'] = '未知';
            }
            if(empty($item['nickname']))
            {
                $item['nickname'] = '匿名';
            }
        }

        return $hc_list;
    }


    /**
     * 获取知识点排行榜接口
     *
     * @category 课程模块
     * @param $openid
     * @param $category_id
     * @param int $limit
     * @return array|\yii\db\ActiveRecord[]
     * @throws \Exception
     */
    public function get_knowledge_point_rank($openid, $category_id, $type = 0, $limit = 4) {

        if (!$openid || !is_string($openid)) {
            throw new \Exception('参数错误！');
        }

        if (!empty($category_id) && !is_numeric($category_id)) {
            throw new \Exception('category_id参数错误');
        }

        $uniacid = Request::getInstance()->uniacid;
        $user = ShopMember::getModel($openid);
        if (empty($user)) {
            throw new \Exception('openid参数错误!');
        }
        $limit = 20;
        $condition = [
            'and',
            ['el_ex_exam.exam_type' => 1],
            ['el_ex_exam.uniacid'   => $uniacid],
            ['like', 'el_ex_question.fullcategorypath', $category_id]
        ];

        //判断是否是好友排行榜
        if($type) {
            $f_id = [];
            $uid = $user->id;
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
        }


        $hc_list = \common\modules\course\models\ExUserAnswer::find()
            ->where($condition)
            ->select([
                'el_ex_user_answer.user_id',
                'sum(`el_ex_user_answer`.`user_score`) as score',
                'ims_new_shop_member.avatar',
                'ims_new_shop_member.nickname',
                'ims_new_shop_member.level',
            ])
            ->joinWith(['member', 'exam', 'question'],false)
            ->groupBy(['user_id'])
            ->orderBy('score desc')
            ->limit($limit)
            ->asArray()
            ->all();


        $default_title = '入门新手';
        $titles = [];
        $level_require = [];
        $member_level = MemberLevel::fetchAll([['>', 'prestige', 0], 'uniacid' => $uniacid],'prestige desc');
        if (!empty($member_level)) {
            foreach ($member_level as $key => $val) {
                $titles[$key] = $val['levelname'];
                $level_require[$key] = intval($val['id']);
            }
        }

        foreach ($hc_list as &$v) {
            $level = intval($v['level']);
            $index = array_search($level, $level_require);
            if ($index !== false) {
                $v['title'] = $titles[$index];
            } else {
                $v['title'] = $default_title;
            }
            if(empty($v['avatar']))
            {
                $v['avatar'] = 'https://xsc.jutouit.com/static/images/avatar.png';
            }
            if(empty($v['city']))
            {
                $v['city'] = '未知';
            }
            if(empty($v['nickname']))
            {
                $v['nickname'] = '匿名';
            }

            $v['remark'] = '答对题目：'.$v['score'];

        }

        return $hc_list;
    }


    /**
     * 获取百人赛好友排行榜
     *
     * @category 课程模块
     * @param $openid
     * @param $exam_id
     * @param int $limit
     * @return array|\yii\db\ActiveRecord[]
     * @throws \Exception
     */
//    public function get_hundred_competition_friend_rank($openid, $exam_id, $limit = 4) {
//        if (!$openid || !is_string($openid)) {
//            throw new \Exception('参数错误！');
//        }
//
//        $uid = ShopMember::fetchField(['openid' => $openid], 'uid');
//
//        if (!$uid) {
//            throw new \Exception('openid参数错误!');
//        }
//
//        $uniacid = Request::getInstance()->uniacid;
//
//        $friends = \common\modules\course\models\ExUserRelationship::find()
//            ->where(['invite_uid' => $uid, 'uniacid' => $uniacid])
//            ->select(['invited_uid as id'])
//            ->asArray()
//            ->all();
//        $invited_you_id = \common\modules\course\models\ExUserRelationship::fetchField(['invited_uid' => $uid, 'uniacid' => $uniacid], 'invite_uid');
//
//        $f_id = ArrayHelper::map($friends,'id','id');
//        $f_id[$invited_you_id] = $invited_you_id;
//
//        $condition = [
//            'and',
//            ['!=', 'user_score', 0],
//            ['in', 'user_id', $f_id],
//            ['exam_type' => 0],
//            ['el_ex_exam.uniacid'   => $uniacid],
//        ];
//
//        if (!empty($exam_id)) {
//            $condition['user_exam'] = $exam_id;
//        }
//
//
//        $hc_list = \common\modules\course\models\ExUserAnswer::find()
//            ->where($condition)
//            ->select([
//                'count(*) as right_num',
//                'el_ex_user_answer.user_id',
//                'ims_new_shop_member.avatar',
//                'ims_new_shop_member.city',
//                'ims_new_shop_member.nickname',
//            ])
//            ->joinWith(['member', 'exam'],false)
//            ->orderBy('right_num desc, uid asc')
//            ->groupBy(['user_id'])
//            ->limit($limit)
//            ->asArray()
//            ->all();
//
//
//
//        return $hc_list;
//    }


    /**
     * 获取知识点分类信息接口
     *
     * @category 课程模块
     * @param $openid
     * @param $category_id
     * @return array
     * @throws \Exception
     */
    public function get_knowledge_point_category_info($openid, $category_id) {
        if (!$openid || !is_string($openid)) {
            throw new \Exception('参数错误！');
        }

        $member = ShopMember::getModel($openid);
        if (!$member) {
            throw new \Exception('openid参数错误!');
        }

        $uniacid = Request::getInstance()->uniacid;
        $return = [];

        $self = \common\modules\course\models\ExQuestionCategory::findOne(
            [
                'ex_question_category_id' => $category_id,
                'uniacid' => $uniacid,
            ]
        );
        if (!$self) {
            throw new \Exception('该类别不存在');
        }

        $return['category_name'] = $self->title;
        $return['category_id'] = $self->ex_question_category_id;
        $return['child'] = $this->get_children_category($category_id, $uniacid);

        return $return;
    }

    /**
     * 获取一级知识点分类接口
     *
     * @category 课程模块
     * @param $openid
     * @return array|\yii\db\ActiveRecord[]
     * @throws \Exception
     */
    public function get_top_knowledge_point_category($openid) {
        if (!$openid || !is_string($openid)) {
            throw new \Exception('参数错误！');
        }

        $member = ShopMember::getModel($openid);
        if (!$member) {
            throw new \Exception('openid参数错误!');
        }

        $uniacid = Request::getInstance()->uniacid;

        $return = \common\modules\course\models\ExQuestionCategory::find()
            ->where([
                'uniacid' => $uniacid,
                'pid' => 0,
            ])
            ->select([
                'title as category_name',
                'ex_question_category_id as category_id'
            ])
            ->asArray()
            ->all();

        return $return;
    }

    /**
     * 获取知识点子类别内部方法
     *
     * @category 课程模块
     * @param $category_id
     * @param $uniacid
     * @return array
     */
    private function get_children_category($category_id, $uniacid) {
        $child = [];

        $category_child = \common\modules\course\models\ExQuestionCategory::fetchAll(
            [
                'pid' => $category_id,
                'uniacid' => $uniacid,
            ]
        );

        if ($category_child) {
            foreach ($category_child as $val) {
                $c = [];
                $c_id = $val['ex_question_category_id'];
                $c_title = $val['title'];
                $c['category_id'] = $c_id;
                $c['category_name'] = $c_title;
                $c['child'] = [];
                if ($c_id) {
                    $c['child'] = $this->get_children_category($c_id, $uniacid);
                }
                $child[] = $c;
            }
        }
        return $child;
    }

    /**
     * 获取首页信息接口
     *
     * @category 课程模块
     * @param $openid
     * @return array
     * @throws \Exception
     */
    public function get_prime_page_info($openid) {
        if (!$openid || !is_string($openid)) {
            throw new \Exception('参数错误！');
        }

        $uniacid = Request::getInstance()->uniacid;
        $user = ShopMember::getModel($openid);

        if (empty($user)) {
            throw new \Exception('openid参数错误!');
        }

        $lid = intval($user->level);
        $point = doubleval($user->credit3);
        $avatar = $user->avatar;
        $nickname = $user->nickname;

        $exp = 0.0;
        /*
        $now_member_level = MemberLevel::fetchField(['level' => $level, ['!=', 'prestige', 0], 'uniacid' => $uniacid], ['prestige']);
        $higher_member_level = MemberLevel::find()->where(['and', ['>', 'level', $level], ['!=', 'prestige', 0], ['>', 'prestige', intval($now_member_level)], ['uniacid' => $uniacid]])->select(['prestige'])->asArray()->one();
        $higher_level_point = $higher_member_level['prestige'];
        if (!empty($now_member_level) && !empty($higher_level_point)) {
            $higher_level_point = doubleval($higher_level_point);
            $now_level_point = doubleval($now_member_level);
            $exp = ( ( $point -  $now_level_point) / ( $higher_level_point - $now_level_point ) );
            $exp = round($exp, 1);
        } else if (empty($higher_member_level)) {
            //如果找不到更高一级，说明已经满级
            $exp = 1.0;
        }
        */
        $now_level = MemberLevel::find()
            ->select('levelname,level,prestige')
            ->where(array('id' => $lid))
            ->asArray()
            ->one();


        if($lid == 0)
        {
            $next_member_level = MemberLevel::find()
                ->select('level,prestige')
                ->where(['and',['>=', 'level', $lid+1],['=','uniacid',$uniacid]])
                ->asArray()
                ->one();
            if($next_member_level)
            {
                $higher_level_point = doubleval($next_member_level['prestige']);
                $now_level_point = doubleval($point);
                if($now_level_point>$higher_level_point)
                {
                    $exp = ($now_level_point-$higher_level_point)/$higher_level_point;
                }
                else
                {
                    $exp = $now_level_point/$higher_level_point;

                }
                if($exp>1)
                    $exp = 1.0;
                $exp = round($exp, 1);
            }
            else
            {
                $exp = 1.0;
            }
        }
        else
        {
            $next_member_level = MemberLevel::find()
                ->select('level,prestige')
                ->where(['and',['>=', 'level', $now_level['level']+1],['=','uniacid',$uniacid]])
                ->asArray()
                ->one();
            $up_member_level = MemberLevel::find()
                ->select('level,prestige')
                ->where(array('level' => $now_level['level'],'uniacid'=>$uniacid))
                ->asArray()
                ->one();
            if($next_member_level&&$next_member_level)
            {

                $exp = (doubleval($point) - doubleval($up_member_level['prestige']))/(doubleval($next_member_level['prestige']) - doubleval($up_member_level['prestige']));
                if($exp>1)
                    $exp = 1.0;
                $exp = round($exp, 1);
            }
            else
            {
                $exp = 1.0;
            }
        }

        $exp = $exp<1.0?$exp:1.0;

        $host_name = \Yii::$app->request->hostInfo;

        $return = [];
        $return['level'] = $lid;
        $mlevel = \common\models\MemberLevel::getByOpenId($openid);
        $return['level_text'] = $lid?$now_level['levelname']:'新手入门';
        $return['point'] = $point;
        $return['exp']   = $exp;
        $return['exp_img_url'] = Helper::getStaticUrl('icons/exp.png');
        $return['avatar'] = $avatar;
        $return['nickname'] = $nickname;
        $return['hc_head_img'] = Url::to('/edu/static/api/img/share/hc_head1.png', true);
        $return['hc_pk_img'] = Url::to('/edu/static/api/img/share/hc_pk.png', true);


        $return['items_zxpc'] = [
            'id' => 'point_exam_image_links',
            'data' =>
                [
                    'img_url' => Url::to('/edu/static/api/img/share/zxpc1.png', true),
                    'wxapp_url' => '/pages/answer/classifyQuery/index?type=knowledgeAnswer',
                ]
        ];

        $return['items'] = [];
        $return['items'][] = [
            'id' => 'two_image_links',
            'data' => [
                // 排行榜
                [
                    'img_url' => Url::to('/edu/static/api/img/share/rank.png', true),
                    'wxapp_url' => '/pages/answer/index/rankingList/index',
                ],
                // 百人赛
                [
                    'img_url' => Url::to('/edu/static/api/img/share/hc_pk.png', true),
                    'wxapp_url' => '/pages/answer/racelist/index',
                ],
            ],
        ];


        /*
        $categories = ExQuestionCategory::find()
            ->where(['uniacid' => $uniacid, 'pid' => 0])
            ->orderBy([
                'sort' => SORT_ASC,
            ])
            ->limit(4)
            ->all();
        $groups = array_chunk($categories, 2);
        foreach ($groups as $group) {
            $tmp = [];
            foreach ($group as $value) {
                $tmp[] = [
                    'ex_question_category_id' => $value->ex_question_category_id,
                    'name' => $value->title,
                    'img_url' => $host_name . $value->img,
                    'wxapp_url' => '/pages/answer/classifyQuery/index?type=knowledgeAnswer',
                ];
            }
            $return['items'][] = [
                'id' => 'two_image_links',
                'data' => $tmp,
            ];
        }
        */


        return $return;
    }

    /**
     * 获取错题数据
     *
     * @category 课程模块
     * @param $openid
     * @param $question_id
     * @return array
     * @throws \Exception
     */
    public function get_error_question($openid, $question_id) {
        if (!$openid || !is_string($openid)) {
            throw new \Exception('参数错误！');
        }

        $uniacid = Request::getInstance()->uniacid;
        $user = ShopMember::getModel($openid);

        if (empty($user)) {
            throw new \Exception('openid参数错误!');
        }

        $uid = $user->id;

        $question = \common\modules\course\models\ExQuestion::findOne(['question_id' =>$question_id]);
        if (empty($question)) {
            \Yii::warning('找不到题目，题目id:'.$question_id,__METHOD__);
            throw new \Exception('找不到题目');
        }

        $answer =  \common\modules\course\models\ExUserAnswer::find()
            ->where(['user_id' => $uid, 'user_question_id' => $question_id, 'uniacid' => $uniacid])
            ->orderBy('user_answer_id desc')
            ->limit(1)
            ->asArray()
            ->all();
        if (empty($answer)) {
            throw new \Exception('当前用户没回答过该题');
        }
        $is_right = $answer->user_score == 0 ? 0 : 1;

        //用户错误选择提取
        $op_chars = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        $user_error_answer = $answer[0]['user_question_answer'];
        if (empty($user_error_answer)) {
            throw new \Exception('解析不到用户答错答案');

        }
        $error_index = [];

        if(strpos($user_error_answer,',') !== false) {
            $user_error_answer = trim($user_error_answer, ',');
            $error_answer = explode(',',$user_error_answer);
            if (!empty($error_answer) && is_array($error_answer)) {
                    foreach ($error_answer as $v) {
                        $index = array_search($op_chars, $v);
                        if ($index !== false) {
                            $error_index[] = $index;
                        }
                    }
            }
        } else {
            $index = 0;
            $index = array_search($user_error_answer, $op_chars);
            if ($index !== false) {
                $error_index[] = $index;
            }
            $error_answer[0] = $user_error_answer;
        }

        $options = \common\modules\course\models\ExOption::fetchAll(['option_question' => $question['question_id']]);
        if(empty($options)) {
            \Yii::warning('找不到题目选项，题目id:'.$question_id,__METHOD__);
            throw new \Exception('不存在题目选项');
        }
        $r_opn = [];
        $r_opn_index = [];
        $q = [];
        $i = 0;

        foreach ($options as $option) {
            $o = [];
            $op_char = $op_chars[$i];
            $o['option_content'] = $option['option_content'];
            $o['option'] = $op_char;
            $q['options'][] = $o;

            if ($option['is_right'] == 1) {
                $r_opn[] = $op_char;
                $r_opn_index[] = array_search($op_char, $op_chars);
            }
            $i++;
        }
        $q['question_type'] = $question->question_type;
        $q['content'] = str_replace('/data/upload',Url::to('/data/upload'),$question->question_content);
        preg_match('/<video.+src=\"?(.+\.(mp4|flv|avi))\"?.+>/i',str_replace('/data/upload',Url::to('/data/upload'),$question->question_qsn_guide2),$r);
        $q['guide_video'] = $r[1];
        $q['guide'] = str_replace('/data/upload',Url::to('/data/upload'),$question->question_qsn_guide);



        $q['user_error_answer'] = $user_error_answer;
        $q['user_error_answer_array'] = $error_answer;
        $q['user_error_answer_index'] = $error_index;
        $q['right_answer'] = join(',', $r_opn);
        $q['right_answer_index'] = $r_opn_index;
        $q['is_right'] = $is_right;
        $return = [];
        $users = [
            'avatar'   => $user->avatar,
            'nickname' =>$user->nickname
        ];
        $return['user'] = $users;
        $return['error_question'] = $q;

        return $return;
    }

    /**
     * get_question_detail_by_examid
     *
     * @category 课程模块
     * @param $openid
     * @param $exam_id
     * @param $type
     * @return array
     * @throws \Exception
     */
    public function get_question_detail_by_examid($openid, $exam_id, $type) {
        if (!$openid || !is_string($openid) || !is_numeric($exam_id)) {
            throw new \Exception('参数错误！');
        }

        $user = ShopMember::getModel($openid);

        if (!$user) {
            throw new \Exception('openid参数错误!');
        }

        $uid = $user->id;
        $uniacid = Request::getInstance()->uniacid;

        $user_exam = ExUserExam::fetchOne([
            'user_id' => $uid,
            'user_exam' => $exam_id,
            'uniacid' => $uniacid
        ]);


        if (!$user_exam) {
            \Yii::warning('该用户没有参加过本次答题, openid：'.$openid.';exam_id:'.$exam_id,__METHOD__);
            throw new \Exception('系统错误');
        }

        $condition = [
            'user_id' => $uid,
            'user_exam_id' => $exam_id,
            'uniacid' => $uniacid
        ];

        if($type) {
            $condition['user_score'] = 0;
        }

        $answers = \common\modules\course\models\ExUserAnswer::find()
            ->where($condition)
            ->with('question')
            ->all();
        ;

        $question_ids = [];
        foreach ($answers as $answer) {
            $question = $answer->question;
            if(!empty($question)) {
                $question_ids[] = $question->getAttribute('question_id');
            }
        }

        //非重复处理
        array_unique($question_ids);

        //联表查询题目和题目对应的选项
        $questions = \common\modules\course\models\ExQuestion::find()->where(['in', 'question_id', $question_ids])->with('options')->all();
        if (!$questions) {
            throw new \Exception('找不到问题');
        }

        //预定义选项字符，有待通过查询数据库优化
        $op_chars = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        $return = [];

        //遍历题目
        foreach ($questions as $question) {
            $options = $question->options;
            $q  = [];
            $op = [];
            $i  = 0;
            //遍历选项
            foreach ($options as $option) {
                $option = $option->getAttributes();
                $o = [];
                $o['option'] = $op_chars[$i];
                $o['option_content'] = $option['option_content'];
                $op[] = $o;
                $i++;
            }
            $q['options'] = $op;
            $q['question_id'] = $question['question_id'];
            $q['question_content'] = str_replace('/data/upload',Url::to('/data/upload'),$question['question_content']);
            $q['guide'] = str_replace('/data/upload',Url::to('/data/upload'),$question['question_qsn_guide']);
            $return[] = $q;
        }


        return [
            'questions' => $return,
        ];
    }
}