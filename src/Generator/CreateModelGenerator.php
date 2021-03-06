<?php
namespace Charsen\Scaffold\Generator;

/**
 * Create Model
 *
 * @author Charsen https://github.com/charsen
 */
class CreateModelGenerator extends Generator
{

    /**
     * @var mixed
     */
    protected $model_path;
    /**
     * @var mixed
     */
    protected $model_relative_path;


    /**
     * @param      $schema_name
     * @param bool $force
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function start($schema_name, $factory = false, $force = false, $trait = false)
    {
        $this->model_path          = $this->utility->getModelPath();
        $this->factory_path        = database_path('factories/');
        $this->model_relative_path = $this->utility->getModelPath(true);

        // 从 storage 里获取模型数据，在修改了 schema 后忘了执行 scaffold:fresh 的话会不准确！！
        $all = $this->filesystem->getRequire($this->utility->getStoragePath() . 'models.php');

        if ( ! isset($all[$schema_name]))
        {
            $this->command->error("Schema File \"{$schema_name}\" could not be found.");
            return false;
        }

        $this->checkOptionsTrait();
        $this->buildExcetions();

        foreach ($all[$schema_name] as $class => $attr)
        {
            $model_path = $this->model_path . $attr['module']['folder'];
            // Model 目录检查，不存在则创建
            if ( ! $this->filesystem->isDirectory($model_path))
            {
                $this->filesystem->makeDirectory($model_path, 0777, true, true);
            }

            $table_attr        = $this->utility->getOneTable($attr['table_name']);
            $fields            = $table_attr['fields'];
            $dictionaries      = $table_attr['dictionaries'];

            // Model 目录及 namespace 处理
            $trait_class       = "{$class}Trait";
            $namespace         = $this->utility->getConfig('model.path') . $attr['module']['folder'];
            $trait_namespace   = $namespace . '/Traits';
            $namespace         = ucfirst(str_replace('/', '\\', $namespace));
            $trait_namespace   = ucfirst(str_replace('/', '\\', $trait_namespace));

            // 文件处理
            $model_file           = $model_path . "/{$class}.php";
            $model_relative_file  = $this->model_relative_path . $attr['module']['folder'] . "/{$class}.php";

            $dictionaries_code    = $this->buildDictionaries($dictionaries, $fields);
            $get_intval_attribute = $this->buildIntvalAttribute($fields);

            // 检查是否存在，存在则不更新
            if ($this->filesystem->isFile($model_file) && ! $force)
            {
                $this->command->error('x Model is existed (' . $model_relative_file . ')');

                // 生成对应的 Trait，即使无 'get_txt_attribute' 也生成，因验证时要用到
                if ($trait) {
                    $this->buildTrait($model_path, $trait_namespace, $trait_class, $attr['table_name'], $dictionaries_code['dictionaries'],
                                      $dictionaries_code['get_txt_attribute'], $get_intval_attribute);
                }

                // 生成对应的 factory 文件并更新 Seeder
                if ($factory) {
                    $this->buildFactory($this->factory_path, $attr['module']['folder'], $attr['table_name'], $class,
                                            $namespace, $fields, $dictionaries, $force);
                }

                continue;
            }

            $casts_code        = $this->buildCasts($fields);
            $hidden            = [];
            $use_trait         = [];
            $use_class         = [];

            // Model Trait
            $use_trait[]   = "{$trait_class}";
            $use_trait[]   = "Optional";
            $use_class[]   = "use " . ucfirst(str_replace('/', '\\', $this->utility->getConfig('model.path'))) . "Traits\Optional;";
            $use_class[]   = "use {$trait_namespace}\\{$trait_class};";

            // 软删除
            if (isset($fields['deleted_at']))
            {
                $use_trait[]   = 'SoftDeletes';
                $use_class[]   = 'use Illuminate\Database\Eloquent\SoftDeletes;';
                $hidden[]      = "'deleted_at'";
            }

            $meta = [
                'author'                => $this->utility->getConfig('author'),
                'date'                  => date('Y-m-d H:i:s'),
                'namespace'             => $namespace,
                'use_class'             => implode("\n", $use_class),
                'use_trait'             => ! empty($use_trait) ? 'use ' . implode(', ', $use_trait) . ';' : '',
                'class'                 => $class,
                'class_name'            => $table_attr['name'] . '模型',
                'table_name'            => $attr['table_name'],
                'casts'                 => $casts_code,
                'appends'               => $this->buildAppends($dictionaries_code['appends']),
                'hidden'                => $this->buildHidden($hidden),
                'fillable'              => $this->buildFillable($fields),
                'dates'                 => $this->buildDates($fields),
                'attributes'            => $this->buildAttributes($fields),
            ];

            $this->filesystem->put($model_file, $this->compileStub($meta));
            $this->command->info('+ ' . $model_relative_file);

            // 生成对应的 Trait，即使无 'get_txt_attribute' 也生成，因验证时要用到
            $this->buildTrait($model_path, $trait_namespace, $trait_class, $meta['table_name'], $dictionaries_code['dictionaries'],
                              $dictionaries_code['get_txt_attribute'], $get_intval_attribute);

            // 生成对应的 factory 文件并更新 Seeder
            if ($factory)
            {
                $this->buildFactory($this->factory_path, $attr['module']['folder'], $attr['table_name'], $class,
                                    $meta['namespace'], $fields, $dictionaries, $force);
            }
        }
    }

    private function checkOptionsTrait()
    {
        $path = $this->model_path . 'Traits/';
        if ( ! $this->filesystem->isDirectory($path))
        {
            $this->filesystem->makeDirectory($path, 0777, true, true);
        }

        $file = $path . 'Optional.php';
        if ( ! $this->filesystem->isFile($file))
        {
            $namespace = $this->utility->getConfig('model.path') . 'Traits';
            $namespace = ucfirst(str_replace('/', '\\', $namespace));
            $meta = [
                'namespace' => $namespace
            ];

            $this->filesystem->put($file, $this->buildStub($meta, $this->getStub('options-trait')));
            $this->command->info('+ ' . $this->model_relative_path . 'Traits/Optional.php');
        }
    }

    /**
     * todo: 生成 字段 默认值
     *
     */
    private function buildAttributes($fields)
    {
        $code = [''];

        foreach ($fields as $field => $v)
        {
            if (isset($v['default']))
            {
                if ($v['default'] === '') {
                    $default = "''";
                } else {
                    $default = $v['default'];
                }

                $code[] = $this->getTabs(2) . "'{$field}' => {$default},";
            }
        }

        if (count($code) <= 1)
        {
            return '';
        }

        $code[] = $this->getTabs(1);

        return implode(PHP_EOL, $code);
    }

    /**
     * 生成 Trait 文件
     *
     * @param string $path
     * @param string $namespace
     * @param string $class
     * @param string $table_name
     * @param string $dictionaries
     * @param string $txt_attribute
     * @param string $intval_attribute
     * @return void
     */
    private function buildTrait($path, $namespace, $class, $table_name, $dictionaries, $txt_attribute, $intval_attribute)
    {
        $path .= '/Traits/';
        // Traits 目录检查，不存在则创建
        if ( ! $this->filesystem->isDirectory($path))
        {
            $this->filesystem->makeDirectory($path, 0777, true, true);
        }

        $trait_file          = $path . $class . '.php';
        $trait_relative_file = str_replace(base_path(), '.', $trait_file);

        $meta = [
            'trait_namespace'       => $namespace,
            'trait_class'           => $class,
            'table_name'            => $table_name,
            'dictionaries'          => $dictionaries,
            'get_txt_attribute'     => $txt_attribute,
            'get_intval_attribute'  => $intval_attribute,
        ];

        $this->filesystem->put($trait_file, $this->compileTraitStub($meta));
        $this->command->info('+ ' . $trait_relative_file);

        return true;
    }

    /**
     * 生成 factory 文件
     *
     * @param string $table_name
     * @param string $class
     * @param string $namespace
     * @return void
     */
    private function buildFactory($path, $folder, $table_name, $class, $namespace, $fields, $dictionaries, $force)
    {
        // Factory 目录检查，不存在则创建
        if ( ! $this->filesystem->isDirectory($path . $folder))
        {
            $this->filesystem->makeDirectory($path . $folder, 0777, true, true);
        }

        $words = array_map(function ($item) {
            return ucfirst($item);
        }, explode('_', $table_name));

        $factory_file           = $this->factory_path . $folder . '/' . implode('', $words) . 'Factory.php';
        $factory_relative_file  = str_replace(base_path(), '.', $factory_file);
        if ($this->filesystem->isFile($factory_file) && ! $force)
        {
            return $this->command->error('x Factory is existed (' . $factory_relative_file . ')');
        }

        $meta = [
            'author'        => $this->utility->getConfig('author'),
            'date'          => date('Y-m-d H:i:s'),
            'model_class'   => $namespace . '\\' . $class,
            'class'         => $class,
            'fields'        => '[]',
        ];

        $codes = ['['];
        foreach ($fields as $field_name => $attr)
        {
            if (\in_array($field_name, ['id', 'deleted_at'])) continue;

            // https://github.com/fzaninotto/Faker
            if (strstr($field_name, '_ids'))
            {
                $rule = "\$faker->numberBetween(1, 3) . ',' . \$faker->numberBetween(4, 7)";
            }
            elseif ($field_name == 'password' || strstr($field_name, '_password'))
            {
                $rule = "\$faker->password";
            }
            elseif ($field_name == 'address' || strstr($field_name, '_address'))
            {
                $rule = "\$faker->address";
            }
            elseif ($field_name == 'mobile' || strstr($field_name, '_mobile'))
            {
                $rule = "\$faker->phoneNumber";
            }
            elseif ($field_name == 'email' || strstr($field_name, '_email'))
            {
                $rule = "\$faker->unique()->safeEmail";
            }
            elseif ($field_name == 'user_name' || $field_name == 'nick_name')
            {
                $rule = "\$faker->userName";
            }
            elseif ($field_name == 'real_name')
            {
                $rule = "\$faker->name(array_random(['male', 'female']))";
            }
            elseif (strstr($field_name, '_code'))
            {
                $rule = "\$faker->numerify('C####')";
            }
            elseif (in_array($attr['type'], ['int', 'tinyint', 'bigint']))
            {
                $rule = "rand(0, 1)";
            }
            elseif ($attr['type'] == 'varchar' || $attr['type'] == 'char')
            {
                $rule = "implode(' ', \$faker->words(2))";
            }
            elseif ($attr['type'] == 'text')
            {
                $rule = "\$faker->text(100)";
            }
            elseif ($attr['type'] == 'date')
            {
                $rule = "\$faker->date()";
            }
            elseif ($attr['type'] == 'datetime' || $attr['type'] == 'timestamp')
            {
                $rule = "\$faker->date() . ' ' . \$faker->time()";
            }
            elseif ($attr['type'] == 'boolean')
            {
                $rule = "\rand(0, 1)";
            }

            if (isset($dictionaries[$field_name])) {
                $temp = array_pluck($dictionaries[$field_name], 1, 0);
                $rule = "\$faker->randomElement([" . implode(', ', array_keys($temp)) . "])";
            }

            $codes[] = $this->getTabs(2) . "'{$field_name}' => {$rule},";
        }
        $codes[] = $this->getTabs(1) . ']';
        $meta['fields'] = implode(PHP_EOL, $codes);

        $this->updateSeeder($meta['model_class']);

        $content        = $this->buildStub($meta, $this->getStub('factory'));
        $this->filesystem->put($factory_file, $content);
        $this->command->info('+ ' . $factory_relative_file);
    }

    /**
     * 更新 Databse Seeder
     *
     * @param string $model_class
     * @return void
     */
    private function updateSeeder($model_class)
    {
        $file       = database_path('seeds/DatabaseSeeder.php');
        $file_txt   = $this->filesystem->get($file);

        // 判断是否已存在于 seeder 中
        if (strstr($file_txt, $model_class)) return false;

        $code       = [];
        $code[]     = "factory({$model_class}::class, 30)->create();";

        $code[]     = PHP_EOL . $this->getTabs(2) . '//:insert_code_here:do_not_delete';
        $code       = implode(PHP_EOL, $code);

        $file_txt   = str_replace("//:insert_code_here:do_not_delete", $code, $file_txt);

        $this->filesystem->put($file, $file_txt);
        $this->command->warn('+ ./database/seeds/DatabaseSeeder.php (Updated)');

        return true;
    }

    /**
     * 生成隐藏属性
     *
     * @param $hidden
     *
     * @return string
     */
    private function buildHidden($hidden)
    {
        if (empty($hidden)) return '';

        $code = [
            $this->getTabs(1) . '/**',
            $this->getTabs(1) . ' * 数组中的属性会被隐藏',
            $this->getTabs(1) . ' * @var array',
            $this->getTabs(1) . ' */',
            $this->getTabs(1) . "protected \$hidden = [" . implode(',', $hidden) . "];",
            '', //空一行
        ];

        return implode("\n", $code);
    }

    /**
     * 生成附加属性
     *
     * @param $appends
     *
     * @return string
     */
    private function buildAppends($appends)
    {
        if (empty($appends)) return '';

        $code = [
            $this->getTabs(1) . '/**',
            $this->getTabs(1) . ' * 追加到模型数组表单的访问器',
            $this->getTabs(1) . ' * @var array',
            $this->getTabs(1) . ' */',
            $this->getTabs(1) . "protected \$appends = [" . implode(', ', $appends) . "];",
            '', //空一行
        ];

        return implode("\n", $code);
    }

    /**
     * 生成 原生类型的属性
     *
     * @param array $fields
     *
     * @return string
     */
    private function buildCasts(array $fields)
    {
        $code = [];

        foreach ($fields as $field_name => $attr)
        {
            if ($attr['type'] == 'boolean')
            {
                $code[] = $this->getTabs(2) . "'{$field_name}' => 'boolean',";
            }
            // todo: 转换更多类型
        }

        if (empty($code)) return '';

        $code[] = $this->getTabs(1) . '];';
        $code[] = '';

        $temp = [
            $this->getTabs(1) . '/**',
            $this->getTabs(1) . ' * 应该被转换成原生类型的属性',
            $this->getTabs(1) . ' * @var array',
            $this->getTabs(1) . ' */',
            $this->getTabs(1) . 'protected $casts = [',
        ];

        return implode("\n", array_merge($temp, $code));
    }

    /**
     * 生成 整形转浮点数处理函数
     *
     * @param array $fields
     *
     * @return string
     */
    private function buildIntvalAttribute(array $fields)
    {
        $code = [];

        foreach ($fields as $field_name => $attr)
        {
            if (isset($attr['format']) && strstr($attr['format'], 'intval:'))
            {
                list($intval, $divisor) = explode(':', trim($attr['format']));
                $function_name = str_replace(' ', '', ucwords(str_replace('_', ' ', $field_name)));

                $code[] = $this->getTabs(1) . '/**';
                $code[] = $this->getTabs(1) . " * {$fields[$field_name]['name']} 浮点数转整数 互转";
                $code[] = $this->getTabs(1) . ' */';
                $code[] = $this->getTabs(1) . "public function set{$function_name}Attribute(\$value)";
                $code[] = $this->getTabs(1) . "{";
                $code[] = $this->getTabs(2) . "\$this->attributes['{$field_name}'] = intval(\$value * {$divisor});";
                $code[] = $this->getTabs(1) . "}";
                $code[] = $this->getTabs(1) . "public function get{$function_name}Attribute()";
                $code[] = $this->getTabs(1) . "{";
                $code[] = $this->getTabs(2) . "return \$this->attributes['{$field_name}'] / {$divisor};";
                $code[] = $this->getTabs(1) . "}";
                $code[] = '';
            }
        }

        return implode("\n", $code);
    }

    /**
     * 生成 dates 代码
     * @param  array  $fields
     * @return string
     */
    private function buildDates(array $fields)
    {
        $code = [];

        foreach ($fields as $field_name => $attr)
        {
            if (in_array($field_name, ['deleted_at', 'created_at', 'updated_at']))
            {
                continue;
            }

            if (in_array($attr['type'], ['date', 'datetime', 'timestamp', 'time']))
            {
                $code[] = "'{$field_name}'";
            }
        }

        return implode(', ', $code);
    }

    /**
     * 生成 fillable 代码
     *
     * @param  array  $fields
     * @return string
     */
    private function buildFillable(array $fields)
    {
        $code = [];

        foreach ($fields as $field_name => $attr)
        {
            if (in_array($field_name, ['id', 'deleted_at', 'created_at', 'updated_at']))
            {
                continue;
            }
            $code[] = "'{$field_name}'";
        }

        return implode(', ', $code);
    }

    /**
     * 生成字典相关的代码，字典数据，附加字段，附加字段值获取函数
     *
     * @param  array  $dictionaries
     * @param  array  $fields
     * @return array
     */
    private function buildDictionaries(array $dictionaries, array $fields)
    {
        $data_code     = ['']; // 先空一行
        $appends_code  = ["'options'"];
        $function_code = [];

        foreach ($dictionaries as $field_name => $attr)
        {
            // 字典数据
            $data_code[] = $this->getTabs(1) . '/**';
            $data_code[] = $this->getTabs(1) . " * 设置 {$fields[$field_name]['name']} 具体值";
            $data_code[] = $this->getTabs(1) . ' * @var array';
            $data_code[] = $this->getTabs(1) . ' */';
            $data_code[] = $this->getTabs(1) . "public \$init_{$field_name} = [";
            foreach ($attr as $alias => $one)
            {
                $data_code[] = $this->getTabs(2) . "'{$one[0]}' => '{$alias}',";
            }
            $data_code[] = $this->getTabs(1) . '];';
            $data_code[] = ''; //空一行

            // 附加字段
            $appends_code[] = "'{$field_name}_txt'";

            // 附加字段值获取函数
            $function_name   = str_replace(' ', '', ucwords(str_replace('_', ' ', $field_name)));
            $function_code[] = $this->getTabs(1) . '/**';
            $function_code[] = $this->getTabs(1) . " * 获取 {$fields[$field_name]['name']} TXT";
            $function_code[] = $this->getTabs(1) . ' * @return string';
            $function_code[] = $this->getTabs(1) . ' */';
            $function_code[] = $this->getTabs(1) . "public function get{$function_name}TxtAttribute()";
            $function_code[] = $this->getTabs(1) . '{';
            $function_code[] = $this->getTabs(2) . "if (\$this->{$field_name} !== NULL)";
            $function_code[] = $this->getTabs(2) . '{';
            $function_code[] = $this->getTabs(3) . "return __('model.' . \$this->init_{$field_name}[\$this->{$field_name}]);";
            $function_code[] = $this->getTabs(2) . '}';
            $function_code[] = '';
            $function_code[] = $this->getTabs(2) . "return '';";
            $function_code[] = $this->getTabs(1) . '}';
            $function_code[] = ''; //空一行

            $function_code[] = $this->getTabs(1) . '/**';
            $function_code[] = $this->getTabs(1) . " * 获取 {$fields[$field_name]['name']} INTEGEL";
            $function_code[] = $this->getTabs(1) . ' * @param string $key';
            $function_code[] = $this->getTabs(1) . ' * @return int';
            $function_code[] = $this->getTabs(1) . ' */';
            $function_code[] = $this->getTabs(1) . "protected function get{$function_name}Int(\$key)";
            $function_code[] = $this->getTabs(1) . '{';
            $function_code[] = $this->getTabs(2) . "\$data = array_flip(\$this->init_{$field_name});";
            $function_code[] = '';
            $function_code[] = $this->getTabs(2) . "if ( ! isset(\$data[\$key]))";
            $function_code[] = $this->getTabs(2) . '{';
            $function_code[] = $this->getTabs(3) . "throw new ModelDictionaryException(\"The [init_{$field_name}] key name [{\$key}] does not exist.\");";
            $function_code[] = $this->getTabs(2) . '}';
            $function_code[] = '';
            $function_code[] = $this->getTabs(2) . "return \$data[\$key];";
            $function_code[] = $this->getTabs(1) . '}';
            $function_code[] = ''; //空一行
        }

        return [
            'dictionaries'      => implode("\n", $data_code),
            'appends'           => $appends_code,
            'get_txt_attribute' => implode("\n", $function_code),
        ];
    }

    /**
     * 编译模板
     *
     * @param $meta
     *
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function compileStub($meta)
    {
        return $this->buildStub($meta, $this->getStub('model'));
    }

    /**
     * 编译 Trait 模板
     *
     * @param $meta
     *
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function compileTraitStub($meta)
    {
        return $this->buildStub($meta, $this->getStub('model-trait'));
    }

    /**
     * 编译 模型字典异常模板
     *
     * @return void
     */
    private function buildExcetions()
    {
        $dictionary = app_path() . '/Exceptions/ModelDictionaryException.php';
        if ( ! $this->filesystem->isFile($dictionary))
        {
            $content = $this->buildStub([], $this->getStub('model-dictinary-exception'));
            $this->filesystem->put($dictionary, $content);
        }

        return true;
    }
}
