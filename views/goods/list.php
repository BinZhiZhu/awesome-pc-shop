<?php

/* @var $this \yii\web\View */

/* @var $content string */

use yii\helpers\Html;
use app\assets\AppAsset;
use app\assets\ElementUI;

ElementUI::register($this);
AppAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <!--    <link rel="shortcut icon" href="--><? //=$host  ?><!--/favicon.ico" type="image/x-icon"/>-->
    <?php $this->head() ?>

</head>
<style>
    .el-row {
        margin-bottom: 20px;
    &:last-child {
         margin-bottom: 0;
     }
    }
    .el-col {
        border-radius: 4px;
    }
    .bg-purple-dark {
        background: #99a9bf;
    }
    .bg-purple {
        background: #d3dce6;
    }
    .bg-purple-light {
        background: #e5e9f2;
    }
    .grid-content {
        text-align: center;
        border-radius: 4px;
        min-height: 40px;
        padding: 10px;
        color: white;
        font-size: 18px;
    }
    .row-bg {
        padding: 10px 0;
        background-color: #f9fafc;
    }

    .block{
        display: flex;
        flex-direction: row;
        justify-content: center;
        padding-top: 20px;
    }
    .tab{
        padding: 20px;
    }
    .el-dialog{
        margin-top: 10px !important;
    }
</style>
<?php $this->beginBody() ?>
<div id="app">
    <div class="tab">
        <el-breadcrumb separator-class="el-icon-arrow-right">
            <el-breadcrumb-item :to="{ path: '/' }">商品管理</el-breadcrumb-item>
            <el-breadcrumb-item>商品列表</el-breadcrumb-item>
        </el-breadcrumb>
    </div>
    <template>
<!--        <el-row>-->
<!--            <el-col :span="24"><div class="grid-content bg-purple-dark">后台用户列表</div></el-col>-->
<!--        </el-row>-->
        <el-table
                :data="tableData"
                border="true"
                style="width: 100%">
            <el-table-column
                    label="编号"
                    align="center"
                    width="100">
                <template slot-scope="scope">{{scope.row.id}}</template>
            </el-table-column>
            <el-table-column
                    label="用户名"
                    align="center"
                    width="150">
                <template slot-scope="scope">{{scope.row.username}}</template>
            </el-table-column>
            <el-table-column
                    label="用户角色"
                    align="center"
                    width="100">
                <template slot-scope="scope">{{scope.row.role_name}}</template>
            </el-table-column>
            <el-table-column
                    label="登录次数"
                    align="center"
                    width="100">
                <template slot-scope="scope">{{scope.row.login_count}}</template>
            </el-table-column>
            <el-table-column
                    label="域名信息"
                    align="center"
                    width="180">
                <template slot-scope="scope">{{scope.row.host_info}}</template>
            </el-table-column>
            <el-table-column
                    label="注册日期"
                    align="center"
                    width="180">
                <template slot-scope="scope">
                    <i class="el-icon-time"></i>
                    <span style="margin-left: 10px">{{ scope.row.register_time }}</span>
                </template>
            </el-table-column>
            <el-table-column
                    label="最近登录时间"
                    align="center"
                    width="180">
                <template slot-scope="scope">
                    <i class="el-icon-time"></i>
                    <span style="margin-left: 10px">{{ scope.row.lastvisit_time }}</span>
                </template>
            </el-table-column>
<!--            <el-table-column-->
<!--                    label="上一次访问IP"-->
<!--                    align="center"-->
<!--                    width="120">-->
<!--                <template slot-scope="scope">{{scope.row.lastvisit_ip}}</template>-->
<!--            </el-table-column>-->
            <el-table-column
                    label="操作"
                    align="center"
            >
                <template slot-scope="scope">
                    <el-button
                            size="mini"
                            @click="handleEdit(scope.$index, scope.row)">查看</el-button>
                    <el-button
                            size="mini"
                            type="danger"
                            @click="handleDelete(scope.$index, scope.row)">删除</el-button>
                </template>
            </el-table-column>
        </el-table>
        <el-dialog
                title="用户信息"
                :visible.sync="dialogUserInfoVisible"
                width="32%"
                center
                :before-close="handleClose"
        >
            <el-form :model="userForm" :rules="userRules" ref="userForm">
                <el-form-item label="编号" :label-width="formLabelWidth" prop="username">
                    <el-input v-model="user.id" disabled></el-input>
                </el-form-item>
            </el-form>
            <el-form :model="userForm" :rules="userRules" ref="userForm">
                <el-form-item label="账号" :label-width="formLabelWidth" prop="username">
                    <el-input v-model="user.username" disabled></el-input>
                </el-form-item>
            </el-form>
            <el-form :model="userForm" :rules="userRules" ref="userForm">
                <el-form-item label="角色" :label-width="formLabelWidth" prop="role">
                    <el-input v-model="user.role_name" disabled></el-input>
                </el-form-item>
            </el-form>
            <el-form :model="userForm" :rules="userRules" ref="userForm">
                <el-form-item label="登录次数" :label-width="formLabelWidth" prop="role">
                    <el-input v-model="user.login_count" disabled></el-input>
                </el-form-item>
            </el-form>
            <span slot="footer" class="dialog-footer">
                    <el-button type="primary" @click="handleClose">关闭</el-button>
            </span>
        </el-dialog>
    </template>
    <div class="block">
        <el-pagination
                @prev-click="prev_click"
                @next-click="next_click"
                :page-size="10"
                :pager-count="11"
                layout="prev, pager, next"
                :total="total">
        </el-pagination>
    </div>
</div>
<script>
    axios.defaults.baseURL = "";
    const router = new VueRouter({
        routes: [
            {path: '/site', name: 'indexPage'},
        ]
    });

    new Vue({
        el: '#app',
        router,
        data() {
            //检验手机号码
            var validateMobile= (rule, value, callback) => {
                if(value){
                    if(!(/^1[3456789]\d{9}$/.test(value))){
                        callback(new Error('手机号码格式有误，请重新填写'));
                    }
                }
            };
            var validateEmail = (rule,vaule,callback) =>{
                if(vaule){
                    var pattern = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;
                    if(!pattern.test(vaule)){
                        callback(new Error('邮箱格式有误，请重新填写'));
                    }
                }
            };
            return {
                page:1,
                total:"",
                dialogUserInfoVisible: false,
                formLabelWidth: '120px',
                user: {},
                userForm: {
                    email: '',
                    address: '',
                    mobile: '',
                    gender: '',
                },
                //校验规则
                userRules: {
                    mobile: [
                        {
                            validator: validateMobile,
                            trigger: 'blur'
                        }
                    ],
                    email: [
                        {
                            validator: validateEmail,
                            trigger: 'blur'
                        }
                    ]
                },
                tableData: [
                    {
                        id: 0,
                        lastvisit_ip: "",
                        lastvisit_time: "",
                        login_count: "4",
                        password: "",
                        register_ip: "",
                        register_time: "",
                        role: "",
                        salt: "",
                        status: "",
                        username: "",
                    }
                ]
            };
        },
        created: function () {
            // `this` 指向 vm 实例
            let url = '<?php echo \yii\helpers\Url::toRoute('backend/get-user-list');?>';
            axios.post(url)
                .then(response => {
                    const resp = response.data;
                    console.log('success', resp);
                    this.tableData = resp.result.list
                    this.total = resp.result.total
                })
                .catch(error => {
                    console.log(error)
                });
        },
        methods: {
            handleClose(done) {
                this.dialogUserInfoVisible = false
            },
            saveInfoSuccess(){
                this.$notify({
                    title: '保存用户信息成功',
                    message: '',
                    type: 'success',
                });
            },
            prev_click(){
                var page = parseInt(app.page)-1;
                let url = '<?php echo \yii\helpers\Url::toRoute('backend/get-user-list');?>';
                axios.post(url)
                    .then(response => {
                        const resp = response.data;
                        console.log('success', resp);
                        this.tableData = resp.result.list
                        this.total = resp.result.total
                    })
                    .catch(error => {
                        console.log(error)
                    });
            },
            next_click(){
                var page = parseInt(app.page)+1;
                let url = '<?php echo \yii\helpers\Url::toRoute('backend/get-user-list');?>';
                axios.post(url)
                    .then(response => {
                        const resp = response.data;
                        console.log('success', resp);
                        this.tableData = resp.result.list
                        this.total = resp.result.total
                    })
                    .catch(error => {
                        console.log(error)
                    });
            },
            current_change(e){
            },
            alertMessage(msg, close, type) {
                this.$message({
                    showClose: close,
                    message: msg,
                    type: type
                });
            },
            handleEdit(index, row) {
                console.log(index, row);
                this.dialogUserInfoVisible = true
                this.user = row
            },
            //提交用户信息表单
            submitUserForm(formName) {
                console.log('submitUserForm',formName)
                let url = '<?php echo \yii\helpers\Url::toRoute('pc/edit-user-info');?>';
                const postData = {
                    gender:this.user.gender,
                    email: this.user.email,
                    mobile: this.user.mobile,
                    address: this.user.address,
                    avatar: this.realAvatar
                };
                let param = new URLSearchParams();
                param.append('gender', postData.gender);
                param.append('email', postData.email);
                param.append('mobile', postData.mobile);
                param.append('address', postData.address);
                param.append('avatar', postData.avatar);
                param.append('user_id', this.user.id);
                console.log('保存用户信息提交过去的参数', postData)
                axios.post(url, param)
                    .then(response => {
                        console.log('保存用户信息成功', response);
                        if (response.data.code === 200) {
                            this.saveInfoSuccess()
                            this.dialogUserInfoVisible = false
                            this.getUserInfo()
                        }else {
                            this.alertMessage(response.data.message, true, 'error');
                        }
                    })
                    .catch(error => {
                        console.log(error)
                    });
            },
            //重置表单 移除校验结果
            resetForm(formName) {
                this.$refs[formName].resetFields();
            },
            handleDelete(index, row) {
                console.log(index, row);
                let url = '<?php echo \yii\helpers\Url::toRoute('backend/delete-user');?>';
                let param = new URLSearchParams();
                const postdata = {
                    id: row.id
                }
                param.append('id', postdata.id);
                console.log('--删除接口--',param)
                axios.post(url,param)
                    .then(response => {
                        const resp = response.data;
                        console.log('success', resp);
                        this.alertMessage(resp.message,true,resp.code === 200 ? 'success': 'error')
                    })
                    .catch(error => {
                        console.log(error)

                    });
            }
        },
    });
</script>

<?php $this->endBody() ?>
</html>
<?php $this->endPage() ?>
