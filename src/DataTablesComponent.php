<?php

namespace amenophis1er\yii2datatables;

use InvalidArgumentException;
use Opis\Closure\SerializableClosure;
use Yii;
use yii\base\Component;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\db\ActiveQuery;
use yii\web\View;

/**
 * DataTablesComponent class integrates the DataTables jQuery plugin with the Yii2 framework.
 */
class DataTablesComponent extends Component
{
    const HTTP_METHOD_GET  = 'get';
    const HTTP_METHOD_POST = 'post';
    
    private static $uniqueId;
    public $callback;
    
    /**
     * @var array
     */
    private $columns;
    
    private $httpMethod = 'POST';
    
    
    private static $id;
    
    /**
     * @var ActiveQuery|array The original query or data array.
     */
    private $originalData;
    
    /**
     * Returns the callback function.
     *
     * @return callable|null The callback function or null if not set.
     */
    public function getCallback(): ?callable
    {
        return $this->deserializeCallback($this->callback);
    }
    
    /**
     * Sets the original query or data array for the DataTables instance.
     *
     * @param  ActiveQuery|array  $originalData  The original query or data array.
     *
     * @return self
     */
    public function setOriginalData($originalData): self
    {
        $this->originalData = $originalData;
        
        return $this;
    }
    
    /**
     * @return mixed
     */
    public function getHttpMethod()
    {
        return $this->httpMethod;
    }
    
    /**
     * @param  mixed  $httpMethod
     */
    public function setHttpMethod($httpMethod)
    {
        $this->httpMethod = $httpMethod;
        
        return $this;
    }
    
    /**
     * Processes the DataTables request and returns the response data.
     *
     * @param  DataTablesComponent  $datatables  The DataTablesComponent instance.
     * @param  array  $requestParams  The DataTables request parameters.
     *
     * @return array The response data.
     * @throws InvalidArgumentException If the original query is not an instance of ActiveQuery or an array.
     */
    public function process(DataTablesComponent $datatables, array $requestParams): array
    {
        $originalData = $datatables->originalData;
        $callback     = $datatables->getCallback();
        
        if ($originalData instanceof ActiveQuery) {
            $totalRecords  = $this->getTotalRecordsCount($originalData);
            $originalQuery = clone $originalData; // Clone the original query
            
            $query           = $this->applyOrdering($originalQuery, $requestParams);
            $query           = $this->applySearching($query, $requestParams);
            $dataProvider    = $this->createDataProvider($query, $requestParams);
            $recordsFiltered = $this->getRecordsFilteredCount($query);
        } elseif (is_array($originalData)) {
            $arrayData = $originalData;
            $this->applySearchingToArray($arrayData, $requestParams);
            $this->applyOrderingToArray($arrayData, $requestParams);
            
            $totalRecords = count($originalData);
            $recordsFiltered = count($arrayData);
            
            $dataProvider = new ArrayDataProvider([
                'allModels' => $arrayData,
            ]);
        } else {
            throw new InvalidArgumentException("The originalData parameter must be either an instance of yii\db\ActiveQuery or an array.");
        }
        
        $data = $this->prepareData($dataProvider, $callback);
        
        return [
            "draw"            => intval($requestParams['draw'] ?? 0),
            "recordsTotal"    => intval($totalRecords),
            "recordsFiltered" => intval($recordsFiltered),
            "data" => $data,
        ];
    }
    
    /**
     * Registers a DataTables instance for the given model or query.
     *
     * @param  ActiveQuery|array  $modelNameOrActiveQuery  The model or ActiveQuery instance.
     * @param  callable|null  $callback  An optional callback function to modify the data.
     *
     * @return DataTablesComponent The registered DataTablesComponent instance.
     */
    public static function register($modelNameOrActiveQuery, callable $callback = null): self
    {
        $context  = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2)[1] ?? null;
        $uniqueId = $context ? $context['class'].'::'.$context['function'] : 'dt00000';
        self::$id = $uniqueId = md5($uniqueId);
        
        $sessionKey = '__datatables-'.$uniqueId;
        
        self::$uniqueId = $uniqueId;
        $dataTablesRef  = new self();
        $dataTablesRef->setOriginalData($modelNameOrActiveQuery);
        
        $serializableCallback = @new SerializableClosure($callback);
        $serializedCallback   = @serialize($serializableCallback);
        
        $dataTablesRef->setCallback($serializedCallback);
        
        $row = is_array($modelNameOrActiveQuery) ? reset($modelNameOrActiveQuery) : $modelNameOrActiveQuery->asArray()->one();
        
        if ($row && $callback) {
            $row = $callback($row);
        }
        $keys    = array_keys($row ?? []);
        $columns = array_reduce($keys, function ($result, $key) {
            $result[] = ['data' => $key, 'title' => ucfirst(str_replace('_', ' ', $key))];
            
            return $result;
        }, []);
        
        $dataTablesRef->setColumns($columns);
        
        $session = Yii::$app->session;
        $session->set($sessionKey, $dataTablesRef);
        
        return $dataTablesRef;
    }
    
    /**
     * Renders the DataTables HTML table.
     *
     * @param  array  $columns  An optional array of column definitions.
     * @param  array  $tableOptions  An optional array of table options (e.g., id, class, and other HTML attributes).
     *
     * @return void
     */
    public function render(array $columns = [], array $tableOptions = []): void
    {
        if (empty($columns)) {
            $columns = $this->getColumns();
        }
        $url = Yii::$app->urlManager->createUrl(['/datatables/endpoint', 'key' => self::$uniqueId]);
        
        $tableId         = $tableOptions['id'] ?? self::$id ?? 'dataTable';
        $tableClass      = $tableOptions['class'] ?? 'display';
        $tableAttributes = $this->generateHtmlAttributes($tableOptions);
        
        $httpmethod = $this->getHttpMethod();
        
        $columnsJson = json_encode($columns);
        $urlEscaped  = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        
        $script = <<<JS
                $(document).ready(function() {
                    const url = '{$urlEscaped}';
                    const tableId = '{$tableId}';
                    let columnsJson = {$columnsJson};

                    $("#{$tableId}").DataTable({
                        "processing": true,
                        "serverSide": true,
                        "ajax": {
                            "url": url,
                            "type": '{$httpmethod}',
                            "dataSrc": function(json) {
                                return json.data;
                            }
                        },
                        "columns": columnsJson
                    });
                });
                JS;
        
        $view = Yii::$app->getView();
        DataTablesAsset::register($view);
        $view->registerJs($script, View::POS_READY);
        
        echo "<table id=\"{$tableId}\" class=\"{$tableClass}\" {$tableAttributes}></table>";
    }
    
    /**
     * Applies search filtering to an array of data.
     *
     * @param  array  $arrayData  The array of data to filter.
     * @param  array  $requestParams  The DataTables request parameters.
     *
     * @return void
     */
    protected function applySearchingToArray(array &$arrayData, array $requestParams): void
    {
        if (!empty($requestParams['search']['value'])) {
            $searchValue = strtolower($requestParams['search']['value']);
            $searchableColumns = array_filter($requestParams['columns'], function ($column) {
                return $column['searchable'] === 'true';
            });
            
            $arrayData = array_filter($arrayData, function ($item) use ($searchableColumns, $searchValue) {
                foreach ($searchableColumns as $column) {
                    $attribute = $column['data'];
                    if (isset($item[$attribute]) && is_string($item[$attribute]) && strpos(strtolower($item[$attribute]),
                            $searchValue) !== false) {
                        return true;
                    }
                }
                
                return false;
            });
        }
    }
    
    /**
     * Applies ordering to an array of data.
     *
     * @param  array  $arrayData  The array of data to order.
     * @param  array  $requestParams  The DataTables request parameters.
     *
     * @return void
     */
    protected function applyOrderingToArray(array &$arrayData, array $requestParams): void
    {
        if (isset($requestParams['order']) && is_array($requestParams['order'])) {
            $sortColumnIndex = $requestParams['order'][0]['column'];
            $sortDirection   = $requestParams['order'][0]['dir'];
            $sortColumnName  = $requestParams['columns'][$sortColumnIndex]['data'];
            
            usort($arrayData, function ($item1, $item2) use ($sortColumnName, $sortDirection) {
                return ($sortDirection === 'asc')
                    ? strcmp($item1[$sortColumnName], $item2[$sortColumnName])
                    : strcmp($item2[$sortColumnName], $item1[$sortColumnName]);
            });
        }
    }
    
    /**
     * Applies ordering to an ActiveQuery instance.
     *
     * @param  ActiveQuery  $query  The ActiveQuery instance to apply ordering to.
     * @param  array  $requestParams  The DataTables request parameters.
     *
     * @return ActiveQuery The modified ActiveQuery instance.
     */
    protected function applyOrdering(ActiveQuery $query, array $requestParams): ActiveQuery
    {
        if (!empty($requestParams['order']) && is_array($requestParams['order'])) {
            $modelClass    = $query->modelClass;
            $modelInstance = new $modelClass();
            $validAttributes = $modelInstance->attributes();
            
            foreach ($requestParams['order'] as $order) {
                $columnData = $requestParams['columns'][$order['column']]['data'];
                if ($requestParams['columns'][$order['column']]['orderable'] === 'true') {
                    if (!empty($validAttributes) && !in_array($columnData, $validAttributes)) {
                        // Skip ordering by invalid attributes
                        continue;
                    }
                    $query->orderBy([
                        $columnData => strtoupper($order['dir']) === 'DESC' ? SORT_DESC : SORT_ASC,
                    ]);
                }
            }
        }
        
        return $query;
    }
    
    /**
     * Applies search filtering to an ActiveQuery instance.
     *
     * @param  ActiveQuery  $originalQuery  The original ActiveQuery instance.
     * @param  array  $requestParams  The DataTables request parameters.
     *
     * @return ActiveQuery The modified ActiveQuery instance.
     */
    protected function applySearching(ActiveQuery $originalQuery, array $requestParams): ActiveQuery
    {
        $searchValue = $requestParams['search']['value'] ?? '';
        $query       = clone $originalQuery; // Create a clone of the original query
        
        if (!empty($searchValue)) {
            $condition = ['or'];
            
            $modelClass    = $query->modelClass;
            $modelInstance = new $modelClass();
            $validAttributes = $modelInstance->attributes();
            
            foreach ($requestParams['columns'] as $column) {
                if ($column['searchable'] === 'true' && in_array($column['data'], $validAttributes)) {
                    $condition[] = ['like', $column['data'], $searchValue];
                }
            }
            
            if (count($condition) > 1) {
                $query->andFilterWhere($condition);
            }
        }
        
        return $query;
    }
    
    /**
     * Creates a DataProvider instance for the given query and request parameters.
     *
     * @param  ActiveQuery  $query  The ActiveQuery instance.
     * @param  array  $requestParams  The DataTables request parameters.
     *
     * @return ActiveDataProvider The created DataProvider instance.
     */
    protected function createDataProvider(ActiveQuery $query, array $requestParams): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $requestParams['length'] ?? 10,
                'page'     => !empty($requestParams['start']) ? floor($requestParams['start'] / ($requestParams['length'] ?? 10)) : 0,
            ],
        ]);
    }
    
    /**
     * Prepares the data for the response by applying the callback function if provided.
     *
     * @param  DataProvider  $dataProvider  The DataProvider instance containing the data.
     * @param  callable|null  $callback  The callback function to apply to the data.
     *
     * @return array The prepared data.
     */
    protected function prepareData($dataProvider, $callback = null): array
    {
        $data = [];
        foreach ($dataProvider->getModels() as $model) {
            $row = $model->attributes ?? $model;
            
            if (is_callable($callback)) {
                $row = $callback($row);
            }
            $data[] = $row;
        }
        
        return $data;
    }
    
    /**
     * Generates an HTML attribute string from an array of attributes.
     *
     * @param  array  $attributes  The array of attributes.
     *
     * @return string The HTML attribute string.
     */
    protected function generateHtmlAttributes(array $attributes): string
    {
        $htmlParts = [];
        foreach ($attributes as $key => $value) {
            if (!in_array($key, ['id', 'class'])) {
                $htmlParts[] = "$key=\"".htmlspecialchars($value)."\"";
            }
        }
        
        return implode(' ', $htmlParts);
    }
    
    /**
     * Sets the columns for the DataTables instance.
     *
     * @param  array  $columns  The array of column definitions.
     *
     * @return self
     */
    private function setColumns(array $columns): self
    {
        $this->columns = $columns;
        
        return $this;
    }
    
    /**
     * Sets the callback function for the DataTables instance.
     *
     * @param  string|null  $callback  The serialized callback function.
     *
     * @return self
     */
    private function setCallback(?string $callback): self
    {
        $this->callback = $callback;
        
        return $this;
    }
    
    /**
     * Returns the column definitions for the DataTables instance.
     *
     * @return array The column definitions.
     */
    public function getColumns(): array
    {
        return $this->columns;
    }
    
    /**
     * Deserializes the callback function.
     *
     * @param  string|null  $callback  The serialized callback function.
     *
     * @return callable|null The deserialized callback function or null if not set.
     */
    private function deserializeCallback(?string $callback): ?callable
    {
        if ($callback) {
            $callback = @unserialize($callback);
            if ($callback instanceof SerializableClosure) {
                return $callback->getClosure();
            }
        }
        
        return null;
    }
    
    /**
     * Returns the total count of records for the given ActiveQuery.
     *
     * @param  ActiveQuery  $query  The ActiveQuery instance.
     *
     * @return int The total count of records.
     */
    private function getTotalRecordsCount(ActiveQuery $query): int
    {
        return $query->count();
    }
    
    /**
     * Returns the count of filtered records for the given ActiveQuery.
     *
     * @param  ActiveQuery  $query  The ActiveQuery instance.
     *
     * @return int The count of filtered records.
     */
    private function getRecordsFilteredCount(ActiveQuery $query): int
    {
        return $query->count();
    }
}