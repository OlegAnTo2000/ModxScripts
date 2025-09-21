<?php

use MODX\Revolution\Processors\System\Settings\Update as SettingUpdate;
use MODX\Revolution\Processors\Security\Group\Create as UserGroupCreate;
use MODX\Revolution\Processors\Security\ResourceGroup\Create as ResourceGroupCreate;
use MODX\Revolution\Processors\Resource\Create as ResourceCreate;
use MODX\Revolution\Processors\Security\Access\UserGroup\Context\Create as AccessContextCreate;
use MODX\Revolution\Processors\Security\Access\UserGroup\ResourceGroup\Create as AccessResourceGroupCreate;

use MODX\Revolution\modResource;
use MODX\Revolution\modUserGroup;
use MODX\Revolution\modResourceGroup;
use MODX\Revolution\modAccessContext;
use MODX\Revolution\modAccessPolicy;

/* https://itchief.ru/modx/login-registration
- создать группу пользователей Users
- создать группу ресурсов Users
- дать группе пользователей Users доступ к web контексту Load, List and View
- создать ресурсы Регистрация, Подтверждение регистрации, Авторизация, Восстановление пароля, Личный кабинет, Изменение пароля, Редактирование данных
- добавить ресурсы «Личный кабинет», «Изменение пароля» и «Редактирование данных» в группу ресурсов Users
- дать группе анонимов доступ Load only к группе ресурсов Users, чтобы у них выходила ошибка доступ запрещен 403
- задать системную настройку unauthorized_page = страница авторизации
*/

/** @var modX $modx */
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO); // можно LOG_LEVEL_ERROR для жёсткой фильтрации
$modx->setLogTarget('ECHO');              // вывод в консоль (ECHO = stdout)

echo '<pre>';

/**
 * Создать или получить ресурс
 */
function ensureResource(modX $modx, array $fields): ?int {
    $exists = $modx->getObject(modResource::class, ['pagetitle' => $fields['pagetitle']]);
    if ($exists) {
        return (int)$exists->get('id');
    }

    $resp = $modx->runProcessor(ResourceCreate::class, $fields);
    if ($resp->isError()) {
        $modx->log(modX::LOG_LEVEL_ERROR, 'Ошибка создания ресурса ' . $fields['pagetitle'] . ': ' . print_r($resp->getAllErrors(), true));
        return null;
    }
    $obj = $resp->getObject();
    $modx->log(modX::LOG_LEVEL_INFO, 'Создан ресурс: ' . $fields['pagetitle'] . ' (ID ' . $obj['id'] . ')');
    return (int)$obj['id'];
}

// --- Создание группы пользователей Users ---
$userGroup = $modx->getObject(modUserGroup::class, ['name' => 'Users']);
if (!$userGroup) {
    $resp = $modx->runProcessor(UserGroupCreate::class, [
        'name'        => 'Users',
        'description' => 'Зарегистрированные пользователи',
    ]);
    if ($resp->isError()) {
        $modx->log(modX::LOG_LEVEL_ERROR, 'Ошибка при создании группы пользователей: ' . print_r($resp->getAllErrors(), true));
    }
    $userGroup = $modx->getObject(modUserGroup::class, ['name' => 'Users']);
    $modx->log(modX::LOG_LEVEL_INFO, 'Создана группа пользователей Users');
}

// --- Создание группы ресурсов Users ---
$resGroup = $modx->getObject(modResourceGroup::class, ['name' => 'Users']);
if (!$resGroup) {
    $resp = $modx->runProcessor(ResourceGroupCreate::class, [
        'name' => 'Users',
    ]);
    if ($resp->isError()) {
        $modx->log(modX::LOG_LEVEL_ERROR, 'Ошибка при создании группы ресурсов: ' . print_r($resp->getAllErrors(), true));
    }
    $resGroup = $modx->getObject(modResourceGroup::class, ['name' => 'Users']);
    $modx->log(modX::LOG_LEVEL_INFO, 'Создана группа ресурсов Users');
}

// --- Создаём ресурсы ---
$resourcesConfig = [
	[
		'pagetitle' => 'Регистрация',
		'alias' => 'register',
		'published' => 1,
		'template' => 0,
		'content' => '<div class="container pt-3">
  <div class="card text-dark bg-white mx-auto mb-3" style="max-width: 30rem;">
    <div class="card-header">Регистрация</div>
    <div class="card-body">
      [[!+error.message:eq=``:then=`
        <form class="form" action="[[~[[*id]]]]" method="post">
        <input type="hidden" name="nospam" id="nospam" value="[[!+reg.nospam]]" />
        <div class="mb-3">
          <label for="fullname" class="form-label">Имя</label>
          <input type="text" name="fullname" class="form-control[[!+reg.error.fullname:notempty=` is-invalid`]]" value="[[!+reg.fullname]]">
          <div class="invalid-feedback">[[!+reg.error.fullname]]</div>
        </div>
        <div class="mb-3">
          <label for="email" class="form-label">Email</label>
          <input type="email" name="email" class="form-control[[!+reg.error.email:notempty=` is-invalid`]]" value="[[!+reg.email]]">
          <div class="invalid-feedback">[[!+reg.error.email]]</div>
        </div>
        <div class="mb-3">
          <label for="password" class="form-label">Пароль</label>
          <input type="password" name="password" class="form-control[[!+reg.error.password:notempty=` is-invalid`]]" value="[[!+reg.password]]">
          <div class="invalid-feedback">[[!+reg.error.password]]</div>
        </div>
        <div class="mb-3">
          <label for="password_confirm" class="form-label">Введите пароль ещё раз</label>
          <input type="password" name="password_confirm" class="form-control[[!+reg.error.password_confirm:notempty=` is-invalid`]]" value="[[!+reg.password_confirm]]">
          <div class="invalid-feedback">[[!+reg.error.password_confirm]]</div>
        </div>
        <input type="submit" name="login-register-btn" class="btn btn-primary" value="Отправить">
      </form>
      `:else=`[[!+error.message]]`]]
    </div>
  </div>
</div> [[!Register?
  &submitVar=`login-register-btn`
  &activation=`1`
  &activationEmailSubject=`Подтверждение регистрации`
  &activationResourceId=`3`
  &successMsg=`Спасибо за регистрацию. На вашу электронную почту [[!+reg.email]] отправлено письмо, содержащее ссылку, необходимую для активации аккаунта. Перейдите по ссылке в письме, чтобы завершить процедуру регистрации.`
  &usergroups=`Users`
  &usernameField=`email`
  &passwordField=`password`
  &validate=`nospam:blank,
    password:required:minLength=^8^,
    password_confirm:password_confirm=^password^,
    fullname:required,
    email:required:email`
  &placeholderPrefix=`reg.`
]]'
	],
	[
		'pagetitle' => 'Подтверждение регистрации',
		'alias' => 'confirm',
		'published' => 1,
		'template' => 0,
		'content' => '[[!ConfirmRegister? &authenticate=`1` &redirectTo=`Личный кабинет` &errorPage=`Личный кабинет`]]'
	],
	[
		'pagetitle' => 'Авторизация',
		'alias' => 'login',
		'published' => 1,
		'template' => 0,
		'content' => '[[!Login]]'
	],
	[
		'pagetitle' => 'Восстановление пароля',
		'alias' => 'forgot',
		'published' => 1,
		'template' => 0,
		'content' => '[[!ForgotPassword]]'
	],
	[
		'pagetitle' => 'Личный кабинет',
		'alias' => 'profile',
		'published' => 1,
		'template' => 0,
		'content' => '<h2>Личный кабинет</h2>'
	],
	[
		'pagetitle' => 'Изменение пароля',
		'alias' => 'changepass',
		'published' => 1,
		'template' => 0,
		'content' => '[[!ChangePassword]]'
	],
	[
		'pagetitle' => 'Редактирование данных',
		'alias' => 'editprofile',
		'published' => 1,
		'template' => 0,
		'content' => '[[!UpdateProfile]]'
	],
];

$createdIds = [];
foreach ($resourcesConfig as $cfg) {
    $id = ensureResource($modx, $cfg);
    if ($id) {
        $createdIds[$cfg['pagetitle']] = $id;
    }
}

// --- Доступ группы Users к контексту web ---
$policy = $modx->getObject(modAccessPolicy::class, ['name' => 'Load, List and View']);
if (!$policy) {
    $modx->log(modX::LOG_LEVEL_ERROR, 'Политика "Load, List and View" не найдена!');
} else {
    $criteria = [
        'target'          => 'web',
        'principal_class' => modUserGroup::class,
        'principal'       => $userGroup->get('id'),
        'policy'          => $policy->get('id'),
    ];
    $access = $modx->getObject(modAccessContext::class, $criteria);
    if ($access) {
        $modx->log(modX::LOG_LEVEL_INFO, 'Доступ Users к web уже существует');
    } else {
        $access = $modx->newObject(modAccessContext::class, array_merge($criteria, [
            'authority' => 9999,
        ]));
        if ($access->save()) {
            $modx->log(modX::LOG_LEVEL_INFO, 'Создан доступ Users к web (политика ID ' . $policy->get('id') . ')');
        } else {
            $modx->log(modX::LOG_LEVEL_ERROR, 'Ошибка при сохранении доступа Users к web');
        }
    }
}


// Анонимы к Users группе ресурсов
$access = $modx->getObject(modAccessResourceGroup::class, [
    'target'          => $resGroup->get('id'),
    'context_key'     => 'web',
    'principal'       => 0,
    'principal_class' => \MODX\Revolution\modUserGroup::class,
]);
if (!$access) {
    $access = $modx->newObject(modAccessResourceGroup::class, [
        'target'          => $resGroup->get('id'),
        'context_key'     => 'web',
        'principal'       => 0,
        'principal_class' => \MODX\Revolution\modUserGroup::class,
        'authority'       => 9999,
        'policy'          => ($modx->getObject(\MODX\Revolution\modAccessPolicy::class, ['name' => 'Load Only']))->get('id')
    ]);
    $access->save();
    $modx->log(modX::LOG_LEVEL_INFO, 'Анонимам создан доступ Load Only к Users напрямую через объект');
} else {
    $modx->log(modX::LOG_LEVEL_INFO, 'Доступ анонимов к Users уже есть');
}



// --- Привязка защищённых ресурсов к группе ресурсов Users ---
foreach (['Личный кабинет','Изменение пароля','Редактирование данных'] as $title) {
    if (!empty($createdIds[$title])) {
        $res = $modx->getObject(modResource::class, $createdIds[$title]);
        if ($res) {
            $res->joinGroup($resGroup->get('id'));
            $modx->log(modX::LOG_LEVEL_INFO, "Ресурс {$title} добавлен в группу ресурсов Users");
        }
    }
}

// --- unauthorized_page = Авторизация ---
if (!empty($createdIds['Авторизация'])) {
    $resp = $modx->runProcessor(SettingUpdate::class, [
        'key'   => 'unauthorized_page',
        'value' => $createdIds['Авторизация'],
        'namespace' => 'core'
    ]);
    if ($resp->isError()) {
        $modx->log(modX::LOG_LEVEL_ERROR, 'Ошибка при установке unauthorized_page: ' . print_r($resp->getAllErrors(), true));
    } else {
        $modx->log(modX::LOG_LEVEL_INFO, 'unauthorized_page установлен на Авторизацию (ID ' . $createdIds['Авторизация'] . ')');
    }
}

$modx->cacheManager->refresh();
$modx->log(modX::LOG_LEVEL_INFO, 'Полный цикл подготовки для Login завершён.');
