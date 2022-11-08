
## 基本用法

~~~
$users = User::select(); // All users
$csvExporter = new \yzh52521\thinkCsv\Export();
$csvExporter->build($users, ['email', 'name'])->download();
~~~

## 建立CSV

`$exporter->build($modelCollection, $fields)`需要三个参数。第一个是模型（模型的集合），第二个是要导出的字段名称，第三个是配置，这是可选的。

~~~
$csvExporter->build(User::select(), ['email', 'name', 'created_at']);
~~~

## 输出选项

### 下载

要将文件下载到浏览器:

~~~
$csvExporter->download();
~~~

如果您愿意，可以提供文件名：

~~~
$csvExporter->download('active_users.csv');
~~~

如果没有给出文件名，则将生成带有日期时间的文件名。

###  高级输出

LaraCSV 使用[League CSV](http://csv.thephpleague.com/)。您可以做 League CSV 能做的事情。您可以通过调用获取底层 League CSV writer 和 reader 实例：

~~~
$csvWriter = $csvExporter->getWriter();
$csvReader = $csvExporter->getReader();
~~~

然后你可以做几件事，比如：

~~~
$csvString = $csvWriter->getContent(); // To get the CSV as string
$csvReader->jsonSerialize(); // To turn the CSV in to an array
~~~

有关更多信息，请查看[League CSV 文档](http://csv.thephpleague.com/)。

## 自定义标题

上面的代码示例将生成一个带有标题电子邮件、名称、created_at 和后面的相应行的 CSV。

如果要使用自定义标签更改标题，只需将其作为数组值传递：

~~~
$csvExporter->build(User::select(), ['email', 'name' => 'Full Name', 'created_at' => 'Joined']);
~~~

现在`name`列将显示标题，`Full Name`但它仍然会从`name`模型的字段中获取值。

###  无标题

您还可以取消 CSV 标头：

~~~
$csvExporter->build(User::select(), ['email', 'name', 'created_at'], [
    'header' => false,
]);
~~~

## 修改或添加值

在处理数据库行之前会触发一个钩子。例如，如果您想更改日期格式，您可以这样做。

~~~
$users = User::get();

// Register the hook before building
$csvExporter->beforeEach(function ($user) {
    $user->created_at = date('f', strtotime($user->created_at));
});

$csvExporter->build($users, ['email', 'name' => 'Full Name', 'created_at' => 'Joined']);
~~~

**注意：**如果`beforeEach`回调返回，`false`则整行将从 CSV 中排除。过滤一些行会很方便。

###  添加字段和值

您还可以添加数据库表中不存在的字段并动态添加值：

~~~
// The notes field doesn't exist so values for this field will be blank by default
$csvExporter->beforeEach(function ($user) {
    // Now notes field will have this value
    $user->notes = 'Add your notes';
});

$csvExporter->build($users, ['email', 'notes']);
~~~

## 分块构建

对于可能会消耗更多内存的较大数据集，可以使用构建器实例以块的形式处理结果。类似于行相关的钩子，在这种情况下可以使用块相关的钩子，例如急切加载或类似的基于块的操作。两个钩子之间的行为是相似的；它在每个块之前被调用，并将整个集合作为参数。**如果`false`返回，整个块被跳过，代码继续下一个。**

~~~
// Perform chunk related operations
$export->beforeEachChunk(function ($collection) {
   
});
$export->buildFromBuilder(User::newQuery(),['email', 'name']);
$export->buildFromBuilder(Db::table('user'),['email', 'name']);
~~~

默认块大小设置为 1000 个结果，但可以通过在`$config`传递给`buildFromBuilder`. 示例将块大小更改为 500。

~~~
$export->buildFromBuilder(User::newQuery(),['email', 'name'], ['chunk' => 500]);
~~~

