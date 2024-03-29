<?php

namespace Charsen\Scaffold\Foundation;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $method;
    protected $transform_methods  = ['create' => 'store', 'edit' => 'update'];

    /**
     * Execute an action on the controller
     *
     * @param  string $method
     * @param  array $parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function callAction($method, $parameters)
    {
        $this->method = $method;

        // 假如存在boot方法 就执行中间件之后 先执行boot 再执行action
        // https://github.com/laravel/framework/blob/5.8/src/Illuminate/Routing/ControllerDispatcher.php#L44
        if (method_exists($this, 'boot')) {
            $this->boot();
        }
        // var_dump($parameters);
        // die();
        // return call_user_func_array([$this, $method], $parameters);
        return $this->{$method}(...array_values($parameters));
    }

    /**
     * Check Authorization
     *
     * @param string $method
     * @return \Illuminate\Auth\Access\Response
     */
    protected function checkAuthorization()
    {
        if ( ! config('scaffold.authorization.check')) return true;

        $method     = $this->transform_methods[$this->method] ?? $this->method;

        $this->authorize('acl_authentication', $this->getAclName(static::class . '-' . $method));
    }

    /**
     * 根据配置获取 action 的 acl name
     *
     * @param $str
     *
     * @return bool|string
     */
    private function getAclName($str)
    {
        $str = str_replace(['\\', 'App-Http-Controllers-', 'Controller'], ['-', '', ''], $str);
        $str = strtolower($str);
        if (config('scaffold.authorization.md5'))
        {
            return substr(md5($str), 8, 16);
            // if (config('scaffold.authorization.short_md5'))
            // {
            //     return substr(md5($str), 8, 16);
            // }
            // else
            // {
            //     return md5($str);
            // }
        }

        return $str;
    }

    /**
     * 设置需要转换的动作
     *
     * @param  array $transform
     * @return void
     */
    protected function setTransformMethods(array $transform)
    {
        $this->transform_methods = \array_merge($this->transform_methods, $transform);
    }

    /**
     * 获取当前动作名称，默认情况下优先获取需要转换的名称（便于做权限验证）
     *
     * @param boolean $real
     * @return string
     */
    protected function getMethod($real = false)
    {
        if ($real) return $this->method;

        return $this->transform_methods[$this->method] ?? $this->method;
    }

    /**
     * todo: remove
     *
     * 获取数据库字段的文字
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @param  array $fields  数据库字段
     * @param  array $append  附加的字段（不是已存在的数据库字段，需要额外添加多语言配置）
     * @return array
     */
    public function getDBFieldsTxt($model, array $fields, array $append = [])
    {
        $result = [];
        $fields = \array_merge($fields, $append);
        foreach ($fields as $key) {
            //$result[$key] = __('db.' . $key); // __('validation.attributes.' . $key)
            $column = property_exists($model, 'init_' . $key) ? $key . '_txt' : $key;
            $result[] = [
                'key'   => $column,
                'name'  => __('db.' . $key)
            ];
        }

        return $result;
    }

}
