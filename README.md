## 基于vue+Element-ui+PHP 的后台管理系统

- 前端技术栈：vue
- 后台组件库：Element-ui
- http库：axios (类ajax)
- 后端开发语言：PHP
- 后端开发框架：Yii2
- 数据库服务：Mysql

### 部署
 
1.cd 到该项目文件夹，命令行运行：composer install,如果环境没有安装composer，请先安装composer    
2.本地建立数据库，数据库名称：demo，账号：root，密码：123456。  
3.命令行运行 ./yii migrate 进行数据库迁移，也就是给数据库建表  
4. 运行项目命令：./yii serve   


### TODO LIST

1.前台用户编辑：邮箱、姓名、地址、电话、上传头像  
2.前台用户订单列表  
3.购物车  
4.商品分类展示  
5.商品展示  
6.商品搜索  
7.后台订单列表  
8.后台区分角色显示不同的操作：管理员：【用户列表查看、删除、编辑】订单列表同上 ； 商家：【订单列表查看、发布商品、删除商品、编辑商品】 
9.完善下单逻辑  
10.完善购物车逻辑  
11.商品评价  

### FAQ

如果部署遇到问题，请联系BinZhiZhu

Email: binzhizhu@gmail.com

Blog: http://www.binzhizhu.top




