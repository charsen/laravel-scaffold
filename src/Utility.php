<?php
namespace Charsen\Scaffold;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Laravel Scaffold Utility
 *
 * @author Charsen https://github.com/charsen
 */
class Utility
{
    /**
     * @var string
     */
    protected $prefix = 'scaffold';

    /**
     * @var mixed
     */
    protected $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }
    
    /**
     * 去除接口文档中动作名带有的请示方法后缀
     *
     * @param $action
     *
     * @return mixed
     */
    public function removeActionNameMethod($action)
    {
        if (is_array($action))
        {
            foreach ($action as &$val)
            {
                $val = str_replace(['_get', '_post', '_delete', '_put', '_patch', '_head'], '', $val);
            }
            
            return $action;
        }
        
        return str_replace(['_get', '_post', '_delete', '_put', '_patch', '_head'], '', $action);
    }
    
    /**
     * 根据语言解析
     *
     * @param $string
     *
     * @return array
     */
    public function parseByLanguages($string)
    {
        $languages  = $this->getConfig('languages');
        $data       = [];
        foreach ($languages as $lang)
        {
            preg_match('/'. $lang .':([^\|]*)[\|}]/i', $string, $temp);
            $data[$lang] = empty($temp) ? '' : trim($temp[1]);
        }
        
        return $data;
    }
    
    /**
     * 解析 包名、模块名、控制器名
     *
     * @param $reflection_class
     *
     * @return array
     */
    public function parsePMCNames($reflection_class)
    {
        $data               = [];
        $doc_comment        = $reflection_class->getDocComment();
        
        preg_match('/@package\_name\s(.*)\n/', $doc_comment, $package_name);
        preg_match('/@module\_name\s(.*)\n/', $doc_comment, $module_name);
        preg_match('/@controller\_name\s(.*)\n/', $doc_comment, $controller_name);
        
        $package_name               = empty($package_name) ? '' : $package_name[1];
        $module_name                = empty($module_name) ? '' : $module_name[1];
        $controller_name            = empty($controller_name) ? '' : $controller_name[1];
        
        $data['package']['name']    = $this->parseByLanguages($package_name);
        $data['module']['name']     = $this->parseByLanguages($module_name);
        $data['controller']['name'] = $this->parseByLanguages($controller_name);
        
        return $data;
    }
    
    /**
     * 解析动作多语言名称
     *
     * @param $action
     * @param $reflection_class
     *
     * @return array
     */
    public function parseActionNames($action, $reflection_class)
    {
        $data               = [];
        $reflection_method  = $reflection_class->getMethod($action);
        $doc_comment        = $reflection_method->getDocComment();
        
        preg_match('/@acl\s(.*)\n/', $doc_comment, $name);
        if (empty($name))
        {
            $data['whitelist']  = true;
        }
        else
        {
            $data['name']       = $this->parseByLanguages($name[1]);
        }
        
        return $data;
    }
    
    /**
     * 解析动作第一行作为名称
     *
     * @param $action
     * @param $reflection_class
     *
     * @return string
     */
    public function parseActionName($action, $reflection_class)
    {
        $reflection_method  = $reflection_class->getMethod($action);
        $doc_comment        = $reflection_method->getDocComment();
    
        preg_match_all('#^\s*\*(.*)#m', $doc_comment, $lines);

        return isset($lines[1][0]) ? trim($lines[1][0]) : '';
    }
    
    /**
     * Get Route File Content
     *
     * @param string $name
     *
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getRouteFile($name = 'api')
    {
        $file = base_path('routes/' . $name . '.php');
        return $this->filesystem->get($file);
    }
    
    /**
     * Get RepositoryServiceProvider File Content
     *
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getRepositoryServiceProviderFile()
    {
        $file = app_path('Providers/RepositoryServiceProvider.php');
        return $this->filesystem->get($file);
    }
    
    /**
     * 获取控制器的命令空间列表
     *
     * !!! 只支持 Http/Controllers/ 再往下两级，更深的层级不支持!!!
     *
     * @return array
     */
    public function getControllerNamespaces()
    {
        $base_path = app_path('Http/Controllers/');
        $dirs = $this->filesystem->directories($base_path);
        if (empty($dirs))
        {
            return ['nothing'];
        }
        
        foreach ($dirs as $path)
        {
            $more = $this->filesystem->directories($path);
            $dirs = array_merge($dirs, $more);
        }
        
        foreach ($dirs as &$dir)
        {
            $dir = str_replace($base_path, '', $dir);
        }
        
        return $dirs;
    }
    
    
    /**
     * 获取所有 Schema 文件的名称
     *
     * @return array
     */
    public function getSchemaNames()
    {
        $path  = $this->getDatabasePath('schema');
        $files = $this->filesystem->files($path);
        
        $data  = [];
        foreach ($files as $file)
        {
            if ($file->getBasename('.yaml') != '_fields')
            {
                $data[] = $file->getBasename('.yaml');
            }
        }

        return $data;
    }
    
    /**
     * 获取多语言文件
     *
     * @param string $file_name
     * @param string $language
     * @param bool   $to_string
     *
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getLanguage($file_name = 'validation', $language = 'en', $to_string = false)
    {
        $file = resource_path("lang/{$language}/{$file_name}.php");

        return $to_string ? $this->filesystem->get($file) : $this->filesystem->getRequire($file);
    }
    
    /**
     * 获取多语言文件路径
     *
     * @param string $file_name
     * @param string $language
     * @param bool   $relative
     *
     * @return string
     */
    public function getLanguagePath($file_name = 'validation', $language = 'en', $relative = false)
    {
        $path = resource_path("lang/{$language}/{$file_name}.php");
    
        return $relative ? str_replace(base_path(), '.', $path) : $path;
    }
    
    /**
     * 获取 schema 文件路径
     *
     * @param null $file_name
     * @param bool $relative
     *
     * @return string
     */
    public function getSchemaPatch($file_name = null, $relative = false)
    {
        $path = $this->getDatabasePath('schema', $relative);

        return $file_name == null ? $path : ($path . $file_name);
    }
    
    /**
     * 检查 api 下的文件是否存在
     *
     * @param        $folder_path
     * @param        $file_name
     * @param string $folder
     *
     * @return string 文件的完整物理路径
     */
    public function isApiFileExist($folder_path, $file_name, $folder = 'schema')
    {
        $file = $this->getApiPath($folder) . $folder_path . '/' . $file_name . '.yaml';
        if (!is_file($file))
        {
            throw new InvalidArgumentException('Invalid File Argument (Not Found).');
        }

        return $file;
    }
    
    /**
     * @param $table_name
     *
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getOneTable($table_name)
    {
        $file = $this->getDatabasePath('storage') . "{$table_name}.php";
    
        if (!is_file($file))
        {
            throw new InvalidArgumentException('Invalid Argument (Not Found).');
        }
        
        return $this->filesystem->getRequire($file);
    }
    
    /**
     * 获取 数据表 数据
     *
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getTables()
    {
        return $this->filesystem->getRequire($this->getDatabasePath('storage') . 'tables.php');
    }
    
    /**
     * 获取资源仓库数据
     *
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getRepositories()
    {
        return $this->filesystem->getRequire($this->getDatabasePath('storage') . 'repositories.php');
    }
    
    /**
     * 获取模型数据
     *
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getModels()
    {
        return $this->filesystem->getRequire($this->getDatabasePath('storage') . 'models.php');
    }
    
    /**
     * 获取控制器数据
     *
     * @param bool $merge_all
     *
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getControllers($merge_all = true)
    {
        $data = $this->filesystem->getRequire($this->getDatabasePath('storage') . 'controllers.php');
        if (! $merge_all)
        {
            return $data;
        }
        
        $result = [];
        foreach ($data as $schema_file => $controllers)
        {
            foreach ($controllers as $class => $attr)
            {
                $result[$class] = $attr;
            }
        }
        
        return $result;
    }
    
    /**
     * 获取多语言字段数据
     *
     * @return array
     */
    public function getLangFields()
    {
        $file = $this->getDatabasePath('schema') . '_fields.yaml';
        if ( ! $this->filesystem->isFile($file))
        {
            return [];
        }
        
        $yaml      = new Yaml;
        $yaml_data = $yaml::parseFile($file);
        $fields    = isset($yaml_data['append_fields'])
                        ? array_merge($yaml_data['table_fields'], $yaml_data['append_fields'])
                        : $yaml_data['table_fields'];
        
        return empty($fields) ? [] : $fields;
    }
    
    /**
     * 获取字段数据
     *
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getFields()
    {
        return $this->filesystem->getRequire($this->getDatabasePath('storage') . 'fields.php');
    }
    
    /**
     * 获取字典数据
     *
     * @param bool $merge_all
     *
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getDictionaries($merge_all = true)
    {
        $dictionaries = $this->filesystem->getRequire($this->getDatabasePath('storage') . 'dictionaries.php');
        if (!$merge_all)
        {
            return $dictionaries;
        }

        $result = [];
        foreach ($dictionaries as $table_name => $data)
        {
            foreach ($data as $field_name => $attr)
            {
                $result[$field_name] = $attr;
            }
        }

        return $result;
    }
    
    /**
     * 获取字典里的所有词
     *
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getDictionaryWords()
    {
        $dictionaries = $this->getDictionaries(false);
        $result = [];
        foreach ($dictionaries as $table_name => $data)
        {
            foreach ($data as $field_name => $words)
            {
                foreach ($words as $alias => $attr)
                {
                    $result[$alias] = ['zh-CN' => $attr[2], 'en' => $attr[1]];
                }
            }
        }
    
        return $result;
    }
    
    /**
     * Get Controller Path
     *
     * @param bool $relative
     *
     * @return mixed|string
     */
    public function getControllerPath($relative = false)
    {
        $path = app_path('Http/Controllers/');
        if ($relative)
        {
            $path = str_replace(base_path(), '.', $path);
        }
        
        return $path;
    }
    
    /**
     * 获取 app Repository 的存储目录
     *
     * @return string
     */
    public function getRepositoryFolder()
    {
        return $this->getConfig('repository.path');
    }
    
    /**
     * Get Repository Path
     *
     * @param bool $relative
     *
     * @return string
     */
    public function getRepositoryPath($relative = false)
    {
        $path = base_path($this->getConfig('repository.path'));
    
        return $relative ? str_replace(base_path(), '.', $path) : $path;
    }
    
    /**
     * 获取 app Model 的存储目录
     *
     * @return string
     */
    public function getModelFolder()
    {
        return $this->getConfig('model.path');
    }
    
    /**
     * Get Model Path
     *
     * @param bool $relative
     *
     * @return string
     */
    public function getModelPath($relative = false)
    {
        $path = base_path($this->getConfig('model.path'));
    
        return $relative ? str_replace(base_path(), '.', $path) : $path;
    }
    
    /**
     * Get Migration Path
     *
     * @param bool $relative
     *
     * @return string
     */
    public function getMigrationPath($relative = false)
    {
        $path = database_path('migrations/');
    
        return $relative ? str_replace(base_path(), '.', $path) : $path;
    }
    
    /**
     * Get Scaffold Database Path
     *
     * @param $folder
     * @param $relative
     *
     * @return string
     */
    public function getDatabasePath($folder = 'schema', $relative = false)
    {
        $path = base_path($this->getConfig('database.' . $folder));
        
        return $relative ? str_replace(base_path(), '.', $path) : $path;
    }
    
    /**
     * Get Scaffold Api Path
     *
     * @param $folder
     * @param $relative
     *
     * @return string
     */
    public function getApiPath($folder = 'schema', $relative = false)
    {
        $path = base_path($this->getConfig('api.' . $folder));
    
        return $relative ? str_replace(base_path(), '.', $path) : $path;
    }

    /**
     * Helper to get the config values.
     *
     * @param  string  $key
     * @param  string  $default
     *
     * @return mixed
     */
    public function getConfig($key, $default = null)
    {
        return config("{$this->prefix}.$key", $default);
    }

}
