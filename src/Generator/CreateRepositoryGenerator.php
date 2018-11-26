<?php
namespace Charsen\Scaffold\Generator;


/**
 * Create Repository
 *
 * @author Charsen https://github.com/charsen
 */
class CreateRepositoryGenerator extends Generator
{

    /**
     * @var mixed
     */
    protected $repository_path;
    /**
     * @var mixed
     */
    protected $repository_relative_path;
    /**
     * @var mixed
     */
    protected $repository_folder;
    protected $model_path;
    protected $model_folder;
    
    /**
     * @param      $schema_name
     * @param bool $force
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function start($schema_name, $force = false)
    {
        $this->repository_path          = $this->utility->getRepositoryPath();
        $this->repository_relative_path = $this->utility->getRepositoryPath(true);
        $this->repository_folder        = $this->utility->getRepositoryFolder();
        $this->model_path               = $this->utility->getModelPath();
        $this->model_folder             = $this->utility->getModelFolder();
        $build_class                    = [];

        // 从 storage 里获取 表名列表，在修改了 schema 后忘了执行 scaffold:fresh 的话会不准确！！
        $all = $this->utility->getRepositories();

        if (!isset($all[$schema_name]))
        {
            return $this->command->error("Schema File \"{$schema_name}\" could not be found.");
        }
        //var_dump($all[$schema_name]);

        foreach ($all[$schema_name] as $repository_class => $attr)
        {
            $table_attrs = $this->utility->getOneTable($attr['table_name']);

            $repository_file          = $this->repository_path . "{$repository_class}Repository.php";
            $repository_relative_file = $this->repository_relative_path . "{$repository_class}Repository.php";
            if ($this->filesystem->isFile($repository_file) && !$force)
            {
                $this->command->error('x Repository is existed (' . $repository_relative_file . ')');
                continue;
            }

            $fields           = $table_attrs['fields'];
            $dictionaries     = $table_attrs['dictionaries'];
            $dictionaries_ids = $this->rebuildDictionaries($dictionaries);
            //var_dump($dictionaries_id);

            // 目录及 namespace 处理
            $original_class       = $repository_class;
            $repository_namespace = $this->dealNameSpaceAndPath($this->repository_path, $this->repository_folder, $repository_class);
            $model_namespace      = $this->dealNameSpaceAndPath($this->model_path, $this->model_folder, $attr['model_class']);

            $meta = [
                'author'    => $this->utility->getConfig('author'),
                'date'      => date('Y-m-d H:i:s'),
                'namespace' => ucfirst($repository_namespace),
                'use_model' => 'use ' . ucfirst($model_namespace) . '\\'. $attr['model_class'] . ';',
                'class'     => $repository_class,
                'rules'     => $this->buildRules($fields, $dictionaries_ids),
            ];

            $this->filesystem->put($repository_file, $this->compileStub($meta));
            $this->command->info('+ ' . $repository_relative_file);

            // Interface
            $repository_interface_file          = $this->repository_path . "{$original_class}RepositoryInterface.php";
            $repository_relative_interface_file = $this->repository_relative_path . "{$original_class}RepositoryInterface.php";
            $this->filesystem->put($repository_interface_file, $this->compileInterfaceStub($meta));
            $this->command->info('+ ' . $repository_relative_interface_file);
    
            $build_class[] = '\\' . $meta['namespace'] . '\\' . $meta['class'] . 'Repository';
        }
    
        $this->updateServiceProvider($build_class);
    }
    
    /**
     * @param array $build_class
     *
     * @return bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function updateServiceProvider(array $build_class)
    {
        $file = $this->utility->getRepositoryServiceProviderFile();
        $code = [];
        foreach ($build_class as $class)
        {
            if (strstr($file, "{$class}::class"))
            {
                continue;
            }
            
            $code[] = (isset($code[0]) ? $this->getTabs(2) : '')
                      . "\$this->app->bind({$class}Interface::class, {$class}::class);";
        }
        
        if (empty($code))
        {
            return true;
        }
        
        $code[] = $this->getTabs(2) . '//:end-bindings:';
        $code   = implode("\n", $code);
        
        $file = str_replace("//:end-bindings:", $code, $file);
        $this->filesystem->put(app_path('Providers/RepositoryServiceProvider.php'), $file);
        $this->command->warn('+ ./app/Providers/RepositoryServiceProvider.php (Updated)');
    
        return true;
    }
    
    /**
     * 生成 检验规
     *
     * @param  array $fields
     * @param array  $dictionaries_ids
     *
     * @return string
     */
    private function buildRules(array $fields, array $dictionaries_ids)
    {
        $rules = $this->rebuildFieldsRules($fields, $dictionaries_ids);
        //var_dump($rules);
    
        // 在列表页附加 分页码参数
        $front_code    = ["'index' => ["];
        $front_code[]  = $this->getTabs(3) . "'page' => 'sometimes|required|integer|min:1',";
        $front_code[]  = $this->getTabs(2) . '],';
        $front_code[]  = $this->getTabs(2) . "'trashed' => [";
        $front_code[]  = $this->getTabs(3) . "'page' => 'sometimes|required|integer|min:1',";
        $front_code[]  = $this->getTabs(2) . '],';
        
        // destroyBatch, restoreBatch
        $end_code      = [$this->getTabs(2) . "'destroyBatch' => ["];
        $end_code[]    = $this->getTabs(3) . "'ids' => 'required|array',";
        $end_code[]    = $this->getTabs(3) . "'force' => 'sometimes|required|boolean',";
        $end_code[]    = $this->getTabs(2) . '],';
        $end_code[]    = $this->getTabs(2) . "'restoreBatch' => [";
        $end_code[]    = $this->getTabs(3) . "'ids' => 'required|array',";
        $end_code[]    = $this->getTabs(2) . '],';
        
        // create & update action
        $create_code = [$this->getTabs(2) . "'create' => ["];
        $update_code = [$this->getTabs(2) . "'update' => ["];

        foreach ($rules as $field_name => $rule)
        {
            $create_code[] = $this->getTabs(3) . "'{$field_name}' => '{$rule}',";
            $update_code[] = $this->getTabs(3) . "'{$field_name}' => 'sometimes|{$rule}',";
        }

        $create_code[] = $this->getTabs(2) . '],';
        $update_code[] = $this->getTabs(2) . '],';
        
        return implode("\n", array_merge($front_code, $create_code, $update_code, $end_code));
    }
    
    /**
     * 重建 字段的规则
     *
     * @param  array $fields
     * @param array  $dictionaries_ids
     *
     * @return array
     */
    private function rebuildFieldsRules(array $fields, array $dictionaries_ids)
    {
        //todo, 根据 索引 unique 附加 unique 规则
        $rules = [];
        foreach ($fields as $field_name => $attr)
        {
            if (in_array($field_name, ['id', 'deleted_at', 'created_at', 'updated_at']))
            {
                continue;
            }
            
            $filed_rules        = [];
            if ($attr['require'])
            {
                $filed_rules[]  = 'required';
            }
            
            if ($attr['allow_null'])
            {
                $filed_rules[]  = 'nullable';
            }
            
            if (in_array($attr['type'], ['int', 'tinyint', 'bigint']))
            {
                // 整数转浮点数时，需要调整为 numeric
                if (isset($attr['format']) && strstr($attr['format'], 'intval:'))
                {
                    $filed_rules[] = 'numeric';
                }
                else
                {
                    $filed_rules[] = 'integer';
                }
                
                if (isset($attr['unsigned']) && ! isset($dictionaries_ids[$field_name]))
                {
                    $filed_rules[] = 'min:0';
                }
            }
    
            if ($attr['type'] == 'boolean')
            {
                $filed_rules[] = 'in:0,1';
            }
            
            if ($attr['type'] == 'date' || $attr['type'] == 'datetime')
            {
                $filed_rules[] = 'date';
            }
            
            if (isset($attr['min_size']) && in_array($attr['type'], ['char', 'varchar']))
            {
                $filed_rules[] = 'min:' . $attr['min_size'];
            }
            
            if (isset($attr['size']) && in_array($attr['type'], ['char', 'varchar']))
            {
                $filed_rules[] = 'max:' . $attr['size'];
            }

            if (isset($dictionaries_ids[$field_name]))
            {
                $filed_rules[] = 'in:' . implode(',', $dictionaries_ids[$field_name]);
            }

            $rules[$field_name] = implode('|', $filed_rules);
        }

        return $rules;
    }
    
    /**
     * 重建 数据字典
     *
     * @param  array $dictionaries [description]
     *
     * @return array [type]               [description]
     */
    private function rebuildDictionaries(array $dictionaries)
    {
        $data = [];
        foreach ($dictionaries as $field_name => $rows)
        {
            foreach ($rows as $one)
            {
                $data[$field_name][] = $one[0];
            }
        }

        return $data;
    }
    
    /**
     * 编译模板
     *
     * @param array $meta
     *
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function compileStub(array $meta)
    {
        return $this->buildStub($meta, $this->getStub('repository'));
    }
    
    /**
     * 编译模板
     *
     * @param array $meta
     *
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function compileInterfaceStub(array $meta)
    {
        return $this->buildStub($meta, $this->getStub('repository-interface'));
    }
}
