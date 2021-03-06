<h2 class="title">
    <div class="group">
        @if ( ! empty($prototype))
            <a href="{{$prototype}}" target="_blank"><i class="icon-prot"></i></a>
        @endif
        <a href="{{ route('api.list', ['f' => $current_folder, 'c' => $current_controller, 'a' => $current_action]) }}" target="_blank">
            <i class="icon-file"></i>
        </a>
    </div>
    <i class="icon-wordbook"></i> {{ $name }}
</h2>
<div class="panel">
    <div class="bd">
        <div class="send-box">
            <a href="javascript:;" class="btn" id="send">发送</a>
            <select id="send_method">
                <option value="POST" <?= strtoupper($request[0]) == 'POST' ? 'selected' : '' ?>>POST</option>
                <option value="GET" <?= strtoupper($request[0]) == 'GET' ? 'selected' : '' ?>>GET</option>
            </select>
            <input type="hidden" id="cache_key" value="{{ $cache_key }}" />
            <input type="hidden" id="host" value="{{ $request_url }}" />
            <input class="txt" id="uri" value="/{{ $request[1] }}" />
        </div>
    </div>
    <div class="hd" style="padding: 10px 0 0 35px">
        <h3>
            <i class="icon-code"></i>
            {{ $check_action }}
        </h3>
    </div>
</div>

@if ( ! empty($desc))
    <div class="alert">
        @foreach ($desc as $v)
            <p>· {{ $v }}</p>
        @endforeach
    </div>
@endif

<div class="panel">
    <div class="hd">
        <a href="javascript:;" class="toggle"><i class="icon-toggle"></i></a>
        <h3>Header</h3>
    </div>
    <div class="bd">
        <div class="tab-bd active">
            <div class="table input" id="request_header">
                <table>
                    <tr>
                        <th width="30"><input type="checkbox" class="checkbox-all"></th>
                        <th width="100">名称</th>
                        <th width="150">key</th>
                        <th>value</th>
                    </tr>
                    <tr>
                        <td><input type="checkbox" class="checkbox" checked /></td>
                        <td><input type="text" class="txt" value="Accept"></td>
                        <td><input type="text" class="txt key" value="Accept"></td>
                        <td><input type="text" class="txt value" value="application/json"></td>
                    </tr>
                    <!--<tr>
                        <td><input type="checkbox" class="checkbox" checked /></td>
                        <td><input type="text" class="txt" value="X-Requested-With"></td>
                        <td><input type="text" class="txt key" value="X-Requested-With"></td>
                        <td><input type="text" class="txt value" value="XMLHttpRequest"></td>
                    </tr>-->

                    @if (isset($header_params['XSRF-TOKEN']))
                    <tr>
                        <td><input type="checkbox" class="checkbox" checked /></td>
                        <td><input type="text" class="txt" value="X-XSRF-TOKEN"></td>
                        <td><input type="text" class="txt key" value="X-XSRF-TOKEN"></td>
                        <td><input type="text" class="txt value" value="{{ $header_params['XSRF-TOKEN'] }}"></td>
                    </tr>
                    @endif

                    @if (isset($header_params['token']))
                    <tr>
                        <td><input type="checkbox" class="checkbox" checked /></td>
                        <td><input type="text" class="txt" value="Token"></td>
                        <td><input type="text" class="txt key" value="Authorization"></td>
                        <td><input type="text" class="txt value" id="auth_token" value="Bearer {{ $header_params['token'] }}"></td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
        <div class="tab-bd">
            <div class="edit-param"></div>
        </div>
    </div>
</div>

@if ( ! empty($url_params))
<div class="panel">
    <div class="hd">
        <a href="javascript:;" class="toggle"><i class="icon-toggle"></i></a>
        <h3>Url Params</h3>
    </div>
    <div class="bd">
        <div class="tab-bd active">
            <div class="table input" id="request_params">
                <table>
                    <tr>
                        <th width="30"><input type="checkbox" class="checkbox-all"></th>
                        <th width="100">名称</th>
                        <th width="150">key</th>
                        <th>value</th>
                        <th>说明</th>
                    </tr>
                    @foreach ($url_params as $key => $v)
                    <tr>
                        <td><input type="checkbox" class="checkbox" {{ ($v['require'] ? 'checked' : '') }}></td>
                        <td><input type="text" value="{{ $v['name'] }}" class="txt" readonly ></td>
                        <td><input type="text" value="{{ $key }}" class="txt key" readonly ></td>
                        <td>
                            @if (isset($v['type']) && $v['type'] == 'radio')
                                <select class="select value">
                                @foreach ($v['options'] as $sk => $sv)
                                    <option value="{{ $sk }}" {{ $sk == $v['value'] ? 'selected' : ''}}>{{ $sk }}: {{ $sv }}</option>
                                @endforeach
                                </select>
                            @else
                                <input type="text" value="{{ $v['value'] }}" class="txt value">
                            @endif
                        </td>
                        <td><input type="text" value="{{ $v['desc'] }}" class="txt" readonly ></td>
                    </tr>
                    @endforeach
                </table>
            </div>
        </div>
        <div class="tab-bd">
            <div class="edit-param"></div>
        </div>
    </div>
</div>
@endif

@if ( ! empty($body_params))
<div class="panel">
    <div class="hd">
        <a href="javascript:;" class="toggle"><i class="icon-toggle"></i></a>
        <h3>Body Params</h3>
    </div>
    <div class="bd">
        <div class="tab-bd active">
            <div class="table input" id="request_params">
                <table>
                    <tr>
                        <th width="30"><input type="checkbox" class="checkbox-all"></th>
                        <th width="100">名称</th>
                        <th width="150">key</th>
                        <th>value</th>
                        <th>说明</th>
                    </tr>
                    @foreach ($body_params as $key => $v)
                    <tr>
                        <td><input type="checkbox" class="checkbox" {{ ($v['require'] ? 'checked' : '') }}></td>
                        <td><input type="text" value="{{ $v['name'] }}" class="txt" readonly ></td>
                        <td><input type="text" value="{{ $key }}" class="txt key" readonly ></td>
                        <td>
                            @if (isset($v['type']) && $v['type'] == 'radio')
                                <select class="select value">
                                @foreach ($v['options'] as $sk => $sv)
                                    <option value="{{ $sk }}" {{ $sk == $v['value'] ? 'selected' : ''}}>{{ $sk }}: {{ $sv }}</option>
                                @endforeach
                                </select>
                            @else
                                <input type="text" value="{{ $v['value'] }}" class="txt value">
                            @endif
                        </td>
                        <td><input type="text" value="{{ $v['desc'] }}" class="txt" readonly ></td>
                    </tr>
                    @endforeach
                </table>
            </div>
        </div>
        <div class="tab-bd">
            <div class="edit-param"></div>
        </div>
    </div>
</div>
@endif
