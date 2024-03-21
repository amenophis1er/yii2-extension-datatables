# Yii2 DataTables Extension

This extension integrates the DataTables jQuery plugin with the Yii2 framework, providing support for efficient
server-side processing of large datasets. It enables Yii2 applications to handle large amounts of data without
compromising performance, making it ideal for projects requiring dynamic table views with extensive functionalities such
as searching, sorting, and pagination.

## Features

- **Server-Side Processing**: Handle large datasets efficiently with server-side data processing.
- **Easy Integration**: Seamlessly integrates with Yii2 projects, allowing for quick setup and use.
- **Customizable Options**: Offers a wide range of customizable DataTables options to meet the specific needs of your
  application.

## Requirements

- Yii2 2.0.15 or higher
- PHP 7.1 or higher

## Installation

Install the extension using Composer:

```
composer require amenophis1er/yii2-datatables
```

## Usage

Integrating the DataTables extension into your Yii2 project is straightforward. Follow the steps below to get started.

### Basic Usage

#### Controller

In your controller, set up your data provider and pass the `DataTablesComponent` object to the view:

```php
use yii\web\Controller;
use amenophis1er\yii2datatables\DataTablesComponent;
use app\models\User;

class SiteController extends Controller
{
    public function actionDemo()
    {
        $query = User::find()
            ->select(['id', 'username', 'email', 'status', 'created_at', 'updated_at'])
            ->where(['like', 'username', 'a%', false]);

        $datatables = \Yii::$app->datatables->register($query, function ($row) {
            // Optionally modify each row data here
            $row['action'] = "<a href='#'>Update</a>";
            unset($row['password_hash']);
            return $row;
        });

        return $this->render('demo', ['datatables' => $datatables]);
    }
}
```

#### View

In your view file, render the DataTables component:

```php
<?php
/* @var $this yii\web\View */
/* @var $datatables amenophis1er\yii2datatables\DataTablesComponent */

$this->title = 'Demo DataTables';
?>

<div class="site-demo">
    <h1><?= \yii\helpers\Html::encode($this->title) ?></h1>
    <div class="container">
        <?= $datatables->setHttpMethod('get')->render() ?>
    </div>
</div>
```

### Customization

To customize DataTables options, you can modify the DataTablesComponent object in your view or controller. For more
details on customization and advanced usage, please refer to
the [DataTables documentation](https://datatables.net/manual/options).

## Contributing

Contributions are welcome! Please refer to the [contributing guidelines](CONTRIBUTING_GUIDELINES) for more
details on how to contribute to this project.

## Troubleshooting

For common issues and questions about using the Yii2 DataTables Extension, see the FAQ section. If you encounter any
problems that are not covered, please open an issue on the GitHub repository.

## License

This extension is released under the MIT License. See the bundled LICENSE file for details.
