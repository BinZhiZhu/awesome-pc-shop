<?php

/* @var $this \yii\web\View */

/* @var $content string */

use yii\helpers\Html;
use app\assets\AppAsset;
use app\assets\ElementUI;

ElementUI::register($this);
AppAsset::register($this);
$this->off(\yii\web\View::EVENT_END_BODY, [\yii\debug\Module::getInstance(), 'renderToolbar']);

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

    &
    :last-child {
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

    .block {
        display: flex;
        flex-direction: row;
        justify-content: center;
        padding-top: 20px;
    }

    .tab {
        padding: 20px;
    }

    .el-dialog {
        margin-top: 10px !important;
    }

    .avatar-uploader .el-upload {
        width: 120px;
        height: 120px;
        border: 1px dashed #d9d9d9;
        border-radius: 6px;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .avatar-uploader .el-upload:hover {
        border-color: #409EFF;
    }

    .avatar-uploader-icon {
        font-size: 28px;
        color: #8c939d;
        width: 178px;
        height: 178px;
        line-height: 178px;
        text-align: center;
    }

    .avatar {
        width: 178px;
        height: 178px
        display: block;
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
                    label="标题"
                    align="center"
                    width="180">
                <template slot-scope="scope">{{scope.row.title}}</template>
            </el-table-column>
            <el-table-column
                    label="副标题"
                    align="center"
                    width="180">
                <template slot-scope="scope">{{scope.row.subtitle}}</template>
            </el-table-column>
            <el-table-column
                    label="缩略图"
                    align="center"
                    width="120">
                <template slot-scope="scope">
                    <el-image
                            style="width: 120px; height: 120px"
                            :src="scope.row.thumb"
                    ></el-image>
                </template>
            </el-table-column>
            <el-table-column
                    label="商品分类"
                    align="center"
                    width="120">
                <template slot-scope="scope">
                    <span style="margin-left: 10px">{{ scope.row.category_title }}</span>
                </template>
            </el-table-column>
            <el-table-column
                    label="价格"
                    align="center"
                    width="80">
                <template slot-scope="scope">
                    <span style="margin-left: 10px">{{ scope.row.price }}</span>
                </template>
            </el-table-column>
            <el-table-column
                    label="库存"
                    align="center"
                    width="80">
                <template slot-scope="scope">{{scope.row.stock}}</template>
            </el-table-column>
            <!--            <el-table-column-->
            <!--                    label="已售数量"-->
            <!--                    align="center"-->
            <!--                    width="100">-->
            <!--                <template slot-scope="scope">{{scope.row.sell_num}}</template>-->
            <!--            </el-table-column>-->
            <el-table-column
                    label="发布时间"
                    align="center"
                    width="180">
                <template slot-scope="scope">
                    <i class="el-icon-time"></i>
                    <span style="margin-left: 10px">{{ scope.row.created_at }}</span>
                </template>
            </el-table-column>
            <el-table-column
                    label="操作"
                    align="center"
            >
                <template slot-scope="scope">
                    <el-button
                            size="mini"
                            @click="handleEdit(scope.$index, scope.row)">编辑
                    </el-button>
                    <el-button
                            size="mini"
                            type="danger"
                            @click="handleDelete(scope.$index, scope.row)">删除
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
        <el-drawer
                title="商品信息"
                size="50%"
                :visible.sync="drawer"
                :direction="direction"
        >
            <el-form :label-position="labelPosition" label-width="100px" :model="ruleForm" :rules="rules"
                     ref="ruleForm">
                <el-form-item label="商品标题" prop="title">
                    <el-input v-model="ruleForm.title"></el-input>
                </el-form-item>
                <el-form-item label="商品副标题" prop="subtitle">
                    <el-input v-model="ruleForm.subtitle"></el-input>
                </el-form-item>
                <el-form-item label="商品分类" prop="category_id">
                    <el-select v-model="ruleForm.category_id" placeholder="请选择">
                        <el-option
                                v-for="item in goodsCategoryList"
                                :key="item.id"
                                :label="item.title"
                                :value="item.id">
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="商品价格" prop="price">
                    <el-input v-model="ruleForm.price"></el-input>
                </el-form-item>
                <el-form-item label="商品图片" prop="thumb">
                    <el-upload
                            class="avatar-uploader"
                            action="https://jsonplaceholder.typicode.com/posts/"
                            :show-file-list="false"
                            :on-success="handleAvatarSuccess"
                            :before-upload="beforeAvatarUpload">
                        <img v-if="ruleForm.thumb" :src="ruleForm.thumb" class="thumb">
                        <i v-else class="el-icon-plus avatar-uploader-icon"></i>
                    </el-upload>
                </el-form-item>
                <el-form-item label="商品库存" prop="stock">
                    <el-input v-model="ruleForm.stock"></el-input>
                </el-form-item>
                <!--                <el-form-item label="已售数量" >-->
                <!--                    <el-input v-model="ruleForm.sell_num"></el-input>-->
                <!--                </el-form-item>-->
                <el-form-item align="center">
                    <el-button type="primary" @click="submitForm('ruleForm')">保存商品</el-button>
                    <el-button @click="resetForm('ruleForm')">重置</el-button>
                </el-form-item>
            </el-form>
        </el-drawer>

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
            var validateMobile = (rule, value, callback) => {
                if (value) {
                    if (!(/^1[3456789]\d{9}$/.test(value))) {
                        callback(new Error('手机号码格式有误，请重新填写'));
                    }
                }
            };
            var validateEmail = (rule, vaule, callback) => {
                if (vaule) {
                    var pattern = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;
                    if (!pattern.test(vaule)) {
                        callback(new Error('邮箱格式有误，请重新填写'));
                    }
                }
            };
            return {
                page: 1,
                total: "",
                goodsCategoryList: [],
                drawer: false,
                direction: 'rtl',
                formLabelWidth: '120px',
                goods: {},
                tableData: [
                    {
                        id: 0,
                        created_at: "",
                        title: '',
                        subtitle: '',
                        price: 0,
                        stock: 0,
                        sell_num: 0,
                        thumb: '',
                    }
                ],
                goods_id: 0,
                ruleForm: {
                    title: '',
                    subtitle: '',
                    stock: 0,
                    sell_num: 0,
                    price: 0,
                    thumb: '',
                    category: ''
                },
                imageUrl: '',
                rules: {
                    title: [
                        {required: true, message: '请输入商品标题', trigger: 'blur'},
                    ],
                    subtitle: [
                        {required: true, message: '请输入商品副标题', trigger: 'blur'},
                    ],
                    price: [
                        {required: true, message: '请输入商品价格', trigger: 'blur'},
                    ],
                    thumb: [
                        {required: true, message: '请上传商品图片', trigger: 'blur'},
                    ],
                    stock: [
                        {required: true, message: '请设置商品库存', trigger: 'blur'},
                    ],
                    category_id: [
                        {required: true, message: '请选择商品分类', trigger: 'blur'},
                    ],
                },
                labelPosition: 'right',
            };
        },
        created: function () {
            // `this` 指向 vm 实例
            this.getGoodsListFunc()
            this.getGoodsCategoryList()
        },
        methods: {
            getGoodsListFunc() {
                let url = '<?php echo \yii\helpers\Url::toRoute('goods/get-goods-list');?>';
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
            //获取商品分类
            getGoodsCategoryList() {
                let url = '<?php echo \yii\helpers\Url::toRoute('goods/get-goods-category-list');?>';
                axios.post(url)
                    .then(response => {
                        const resp = response.data;
                        console.log('获取商品分类列表结果', resp);
                        this.goodsCategoryList = resp.result.list
                    })
                    .catch(error => {
                        console.log(error)
                    });
            },
            handleAvatarSuccess(res, file) {
                console.log('handleAvatarSuccess', res, file)
                this.ruleForm.thumb = URL.createObjectURL(file.raw);
                console.log('imageUrl', this.ruleForm.ruleForm)
                //生成了blob文件
                let url = '<?php echo \yii\helpers\Url::toRoute('pc/upload');?>';
                const data = new FormData();
                data.append('file', file.raw);
                axios.post(url, data)
                    .then(response => {
                        console.log('获取图片上传结果', response.data);
                        const resp = response.data.result;
                        this.imageUrl = resp.url
                    })
                    .catch(error => {
                        console.log(error)
                    });
            },
            beforeAvatarUpload(file) {
                console.log('beforeAvatarUpload', file)
                const isJPG = file.type === 'image/jpeg';
                const isLt2M = file.size / 1024 / 1024 < 2;

                if (!isJPG) {
                    this.$message.error('上传头像图片只能是 JPG 格式!');
                }
                if (!isLt2M) {
                    this.$message.error('上传头像图片大小不能超过 2MB!');
                }
                return isJPG && isLt2M;
            },
            handleClose(done) {
                this.dialogUserInfoVisible = false
            },
            saveInfoSuccess() {
                this.$notify({
                    title: '保存用户信息成功',
                    message: '',
                    type: 'success',
                });
            },
            prev_click() {
                var page = parseInt(app.page) - 1;
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
            next_click() {
                var page = parseInt(app.page) + 1;
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
            current_change(e) {
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
                this.drawer = true
                this.ruleForm = row
                this.goods_thumb = row.thumb;
                this.goods_id = row.id
            },
            submitForm(formName) {
                this.$refs[formName].validate((valid) => {
                    if (valid) {
                        const postData = this.ruleForm
                        postData.thumb = this.imageUrl ? this.imageUrl : this.goods_thumb;
                        console.log('postData', postData)
                        let url = '<?php echo \yii\helpers\Url::toRoute('goods/edit');?>';
                        let param = new URLSearchParams();
                        param.append('title', postData.title);
                        param.append('subtitle', postData.subtitle);
                        param.append('price', postData.price);
                        param.append('stock', postData.stock);
                        param.append('sell_num', postData.sell_num);
                        param.append('thumb', postData.thumb);
                        param.append('category', postData.category_id);
                        param.append('goods_id', this.goods_id);
                        axios.post(url, param)
                            .then(response => {
                                console.log('获取编辑商品结果', response.data);
                                if (response.data.code === 200) {
                                    const resp = response.data.result;
                                    this.$notify({
                                        title: response.data.message,
                                        message: '',
                                        type: 'success',
                                    });
                                    this.drawer = false
                                    this.getGoodsListFunc()
                                } else {
                                    this.alertMessage(response.data.message, true, 'error');
                                }
                            })
                            .catch(error => {
                                console.log(error)
                            });
                    } else {
                        console.log('error submit!!');
                        return false;
                    }
                });
            },
            //重置表单 移除校验结果
            resetForm(formName) {
                this.$refs[formName].resetFields();
            },
            handleDelete(index, row) {
                console.log(index, row);
                let url = '<?php echo \yii\helpers\Url::toRoute('goods/delete-goods');?>';
                let param = new URLSearchParams();
                const postdata = {
                    id: row.id
                }
                param.append('id', postdata.id);
                console.log('--删除接口--', param)
                axios.post(url, param)
                    .then(response => {
                        const resp = response.data;
                        console.log('success', resp);
                        this.alertMessage(resp.message, true, resp.code === 200 ? 'success' : 'error')
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
