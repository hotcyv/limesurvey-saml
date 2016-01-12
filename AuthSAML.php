<?php

/*
 * SAML Authentication plugin for LimeSurvey
 * Copyright (C) 2013 Sixto Pablo Martin Garcia <sixto.martin.garcia@gmail.com>
 * License: GNU/GPL License v2 http://www.gnu.org/licenses/gpl-2.0.html
 * URL: https://github.com/pitbulk/limesurvey-saml
 * A plugin of LimeSurvey, a free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */

/*
 * TODO: Localization
 * 
 */

class AuthSAML extends AuthPluginBase {

    protected $storage = 'DbStorage';
    protected $ssp = null;
    protected $attributes = null;
    static protected $description = 'Plugin para autenticação SAML (Administração e acesso a questionário)';
    static protected $name = 'SAML';
    protected $settings = array(
        'simplesamlphp_path' => array(
            'type' => 'string',
            'label' => 'Path to the SimpleSAMLphp folder',
            'default' => '/var/www/simplesamlphp',
        ),
        'saml_authsource' => array(
            'type' => 'string',
            'label' => 'SAML authentication source',
            'default' => 'limesurvey',
        ),
        'saml_uid_mapping' => array(
            'type' => 'string',
            'label' => 'SAML attributed used as username',
            'default' => 'uid',
        ),
        'saml_mail_mapping' => array(
            'type' => 'string',
            'label' => 'SAML attributed used as email',
            'default' => 'mail',
        ),
        'saml_name_mapping' => array(
            'type' => 'string',
            'label' => 'SAML attributed used as Common name',
            'default' => 'cn',
        ),
        'saml_surname_mapping' => array(
            'type' => 'string',
            'label' => 'SAML attributed used as Surname',
            'default' => 'sn',
        ),
        'saml_givenname_mapping' => array(
            'type' => 'string',
            'label' => 'SAML attributed used as Given name',
            'default' => 'givenName',
        ),
        'authtype_base' => array(
            'type' => 'string',
            'label' => 'Authtype base',
            'default' => 'Authdb',
        ),
        'storage_base' => array(
            'type' => 'string',
            'label' => 'Storage base',
            'default' => 'DbStorage',
        ),
        'pluginIdpAttributes' => array(
            'help' => 'Each IdPAttribute must have the label and options entries',
            'type' => 'json',
            'label' => 'IdP Attributes',
            'editorOptions' => array(
                'name' => 'idpAttributes',
                'mode' => 'tree',
            ),
        ),
        'auto_create_users' => array(
            'type' => 'checkbox',
            'label' => 'Auto create users',
            'default' => true,
        ),
        'auto_create_labelsets' => array(
            'type' => 'checkbox',
            'label' => '- Permissions: Label Sets',
            'default' => false,
        ),
        'auto_create_participant_panel' => array(
            'type' => 'checkbox',
            'label' => '- Permissions: Participant panel',
            'default' => false,
        ),
        'auto_create_settings_plugins' => array(
            'type' => 'checkbox',
            'label' => '- Permissions: Settings & Plugins',
            'default' => false,
        ),
        'auto_create_surveys' => array(
            'type' => 'checkbox',
            'label' => '- Permissions: Surveys',
            'default' => true,
        ),
        'auto_create_templates' => array(
            'type' => 'checkbox',
            'label' => '- Permissions: Templates',
            'default' => false,
        ),
        'auto_create_user_groups' => array(
            'type' => 'checkbox',
            'label' => '- Permissions: User groups',
            'default' => false,
        ),
        'auto_update_users' => array(
            'type' => 'checkbox',
            'label' => 'Auto update users',
            'default' => true,
        )
    );

    protected function get_saml_instance() {
        if ($this->ssp == null) {

            $simplesamlphp_path = $this->get('simplesamlphp_path', null, null, '/var/www/simplesamlphp');

            // To avoid __autoload conflicts, remove limesurvey autoloads temporarily 
            $autoload_functions = spl_autoload_functions();
            foreach ($autoload_functions as $function) {
                spl_autoload_unregister($function);
            }

            require_once($simplesamlphp_path . '/lib/_autoload.php');

            $saml_authsource = $this->get('saml_authsource', null, null, 'limesurvey');
            $this->ssp = new SimpleSAML_Auth_Simple($saml_authsource);

            // To avoid __autoload conflicts, restote the limesurvey autoloads
            foreach ($autoload_functions as $function) {
                spl_autoload_register($function);
            }
        }
        return $this->ssp;
    }

    public function __construct(PluginManager $manager, $id) {
        parent::__construct($manager, $id);

        $this->storage = $this->get('storage_base', null, null, 'DbStorage');
        $this->get_saml_instance();
        $this->attributes = $this->ssp->getAttributes();

        // Here you should handle subscribing to the events your plugin will handle
        $this->subscribe('newUserSession');
        $this->subscribe('newSurveySettings');
        $this->subscribe('beforeActivate');
        $this->subscribe('beforeLogin');
        $this->subscribe('beforeSurveyPage');
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('afterLogout');
    }

    /**
     * Check the required IdP Attribute entries(label and options)
     *
     * @return none
     */
    public function beforeActivate() {
        $oEvent = $this->event;
        $aPluginIdpAttributes = json_decode($this->get('pluginIdpAttributes', null, null, true), true);

        foreach ($aPluginIdpAttributes as $idpAttribute => $values) {
            if (!isset($values['label']) || !isset($values['options']) || !isset($values['help'])) {
                $oEvent->set('success', false);
                $oEvent->set('message', "Problem on IdP Attribute '{$idpAttribute}' : minimal JSON structure (label, options).");
                break;
            }
        }
    }

    public function beforeLogin() {

        if ($this->ssp->isAuthenticated() && isset($this->attributes['eduPersonAffiliation']) && in_array($this->attributes['eduPersonAffiliation'][0], array('faculty', 'employee'))) {
            $this->setAuthPlugin();
            $this->newUserSession();
        }
    }

    /**
     * Create dynamically the IdP options defined at SAML plugin
     *
     * @return none
     */
    public function beforeSurveySettings() {
        $oEvent = $this->event;
        $iSurveyId = $oEvent->get('survey');
        $bUse = $this->get('bUse', 'Survey', $iSurveyId);
        $aSurveyIdpAttributes = json_decode($this->get('surveyIdpAttributes', 'Survey', $iSurveyId), true);
        $aPluginIdpAttributes = json_decode($this->get('pluginIdpAttributes', null, null, true), true);

        $aSettings = array(
            'bUse' => array(
                'type' => 'select',
                'help' => 'Uma vez habilitado, somente usuários do IFSC (Discente, Docente e TAE) terão acesso ao questionário.',
                'options' => array(
                    0 => 'Não',
                    1 => 'Sim'
                ),
                'default' => 0,
                'label' => 'Ativar plugin?',
                'current' => $bUse
        ));

        foreach ($aPluginIdpAttributes as $idpAttribute => $values) {
            $aSettings[$idpAttribute] = array(
                'type' => 'select',
                'help' => $values['help'],
                'options' => $values['options'],
                'label' => $values['label'],
                'current' => $aSurveyIdpAttributes[$idpAttribute],
            );
        }

        $oEvent->set("surveysettings.{$this->id}", array(
            'name' => get_class($this),
            'settings' => $aSettings
        ));
    }

    public function beforeSurveyPage() {

        $oEvent = $this->event;
        $iSurveyId = $oEvent->get('surveyId');
        $bUse = $this->get('bUse', 'Survey', $iSurveyId);

        if ($bUse) { //Only private surveys with authsaml plugin enabled
            if ($this->ssp->isAuthenticated()) { //Only idp users                
                $sLanguage = Yii::app()->request->getParam('lang');
                $aSurveyInfo = getSurveyInfo($iSurveyId, $sLanguage);
                $aSurveyIdpAttributes = array_diff(json_decode($this->get('surveyIdpAttributes', 'Survey', $iSurveyId), true), array('none'));

                if ($this->checkIdpAttributes($aSurveyIdpAttributes)) {
                    $oToken = TokenDynamic::model($iSurveyId)->find('email=:email', array(':email' => $this->getUserMail()));

                    if ($oToken) { //Allow survey access if the token is given
                        if (Yii::app()->request->getParam('token')) {
                            return;
                        }
                        $sToken = $oToken->token;
                    } else {//Creation of the token
                        $oToken = Token::create($iSurveyId);
                        $oToken->firstname = $this->getUserGivenName();
                        $oToken->lastname = $this->getUserSurName();
                        $oToken->email = $this->getUserMail();
                        $oToken->emailstatus = 'OK';
                        $oToken->language = $sLanguage;
                        if ($aSurveyInfo['startdate']) {
                            $oToken->validfrom = $aSurveyInfo['startdate'];
                        }
                        if ($aSurveyInfo['expires']) {
                            $oToken->validuntil = $aSurveyInfo['expires'];
                        }
                        $oToken->save();
                        $iTokenId = $oToken->tid;
                        $sToken = TokenDynamic::model($iSurveyId)->createToken($iTokenId);
                    }
                    if ($sToken) {
                        $surveylink = App()->createAbsoluteUrl("/survey/index/sid/{$iSurveyId}", array('token' => $sToken));
                        header('Location: ' . $surveylink);
                    }
                } else {
                    $aReplacementFields = array();
                    $aReplacementFields["{ADMINNAME}"] = $aSurveyInfo['adminname'];
                    $aReplacementFields["{ADMINEMAIL}"] = $aSurveyInfo['adminemail'];
                    $sLanguage = Yii::app()->request->getParam('lang', '');
                    if ($sLanguage == "") {
                        $sLanguage = Survey::model()->findByPk($iSurveyId)->language;
                    }
                    $aSurveyInfo = getSurveyInfo($iSurveyId, $sLanguage);
                    $sTemplatePath = $aData['templatedir'] = getTemplatePath($aSurveyInfo['template']);
                    $sAttributesRequired = '';
                    $sAttributesReceived = '';
                    foreach ($aSurveyIdpAttributes as $key => $value) {
                        $sAttributesRequired .= "<li>{$key} = \"{$value}\"</li>";
                    }
                    foreach (array_intersect_key($this->attributes, $aSurveyIdpAttributes) as $key => $value) {
                        $sAttributesReceived .= "<li>{$key} = \"{$value[0]}\"</li>";
                    }
                    $sReturnHtml = "<div id='wrapper' class='message tokenmessage'>"
                            . "<h3>Acesso ao questionário não permitido!</h3>\n"
                            . "<p>Informações de usuário necessárias:</p>\n"
                            . "<ul>$sAttributesRequired</ul><br />"
                            . "<p>Informações de usuário recebidas:</p>\n"
                            . "<ul>$sAttributesReceived</ul><br />"
                            . "<p>Entre em contato com o administrador do questionário: {ADMINNAME} ({ADMINEMAIL})</p>"
                            . "</div>\n";
                    $sReturnHtml = ReplaceFields($sReturnHtml, $aReplacementFields);
                    ob_start(function($buffer, $phase) {
                        App()->getClientScript()->render($buffer);
                        App()->getClientScript()->reset();
                        return $buffer;
                    });
                    ob_implicit_flush(false);
                    sendCacheHeaders();
                    doHeader();
                    $aData['thissurvey'] = $aSurveyInfo;
                    $aData['thissurvey'] = $aSurveyInfo;
                    echo templatereplace(file_get_contents($sTemplatePath . '/startpage.pstpl'), array(), $aData);
                    echo templatereplace(file_get_contents($sTemplatePath . '/survey.pstpl'), array(), $aData);
                    echo $sReturnHtml;
                    echo templatereplace(file_get_contents($sTemplatePath . '/endpage.pstpl'), array(), $aData);
                    doFooter();
                    ob_flush();
                    App()->end();
                }
            } else {// Asks idp authentication
                header('Location: ' . $this->ssp->getLoginURL());
            }
        }
    }

    public function afterLogout() {
        $this->ssp->logout(array('ReturnTo' => Yii::app()->getConfig('homeurl')));
    }

    public function getUserName() {
        if ($this->_username == null) {

            if (!empty($this->attributes)) {
                $saml_uid_mapping = $this->get('saml_uid_mapping', null, null, 'uid');
                if (array_key_exists($saml_uid_mapping, $this->attributes) && !empty($this->attributes[$saml_uid_mapping])) {
                    $username = $this->attributes[$saml_uid_mapping][0];
                    $this->setUsername($username);
                }
            }
        }
        return $this->_username;
    }

    public function getUserCommonName() {
        $name = '';

        if (!empty($this->attributes)) {
            $saml_name_mapping = $this->get('saml_name_mapping', null, null, 'cn');
            if (array_key_exists($saml_name_mapping, $this->attributes) && !empty($this->attributes[$saml_name_mapping])) {
                $name = $this->attributes[$saml_name_mapping][0];
            }
        }
        return $name;
    }

    public function getUserGivenName() {
        $givenName = '';

        if (!empty($this->attributes)) {
            $saml_givenname_mapping = $this->get('saml_givenname_mapping', null, null, 'givenName');
            if (array_key_exists($saml_givenname_mapping, $this->attributes) && !empty($this->attributes[$saml_givenname_mapping])) {
                $givenName = $this->attributes[$saml_givenname_mapping][0];
            }
        }
        return $givenName;
    }

    public function getUserSurName() {
        $surName = '';

        if (!empty($this->attributes)) {
            $saml_surname_mapping = $this->get('saml_surname_mapping', null, null, 'sn');
            if (array_key_exists($saml_surname_mapping, $this->attributes) && !empty($this->attributes[$saml_surname_mapping])) {
                $surName = $this->attributes[$saml_surname_mapping][0];
            }
        }
        return $surName;
    }

    public function getUserMail() {
        $mail = '';

        if (!empty($this->attributes)) {
            $saml_mail_mapping = $this->get('saml_mail_mapping', null, null, 'mail');
            if (array_key_exists($saml_mail_mapping, $this->attributes) && !empty($this->attributes[$saml_mail_mapping])) {
                $mail = $this->attributes[$saml_mail_mapping][0];
            }
        }
        return $mail;
    }

    public function checkIdpAttributes(array $aSurveyIdpAttributes) {

        $check = true;

        if (!empty($this->attributes)) {

            foreach ($aSurveyIdpAttributes as $surveyIdpAttribute => $value) {
                if (!array_key_exists($surveyIdpAttribute, $this->attributes) || empty($this->attributes[$surveyIdpAttribute]) || strtolower($this->attributes[$surveyIdpAttribute][0]) !== strtolower($value)) {
                    $check = false;
                    break;
                }
            }
        }

        return $check;
    }

    public function newSurveySettings() {
        $oEvent = $this->getEvent();
        $iSurveyId = $oEvent->get('survey');
        $aSettings = $oEvent->get('settings');
        
        //check tokens table when YES
        if ($aSettings['bUse'] == 1 && !tableExists("tokens_{$iSurveyId}")) {
            Token::createTable($iSurveyId);
        }

        $this->set('bUse', array_shift($aSettings), 'Survey', $oEvent->get('survey'));
        $this->set('surveyIdpAttributes', json_encode($aSettings), 'Survey', $oEvent->get('survey'));
    }

    public function newUserSession() {
        if ($this->ssp->isAuthenticated()) {

            $sUser = $this->getUserName();
            $_SERVER['REMOTE_USER'] = $sUser;

            $password = createPassword();
            $this->setPassword($password);

            $name = $this->getUserCommonName();
            $mail = $this->getUserMail();

            $oUser = $this->api->getUserByName($sUser);
            if (is_null($oUser)) {
                // Create user
                $auto_create_users = $this->get('auto_create_users', null, null, true);
                if ($auto_create_users) {

                    $iNewUID = User::model()->insertUser($sUser, $password, $name, 1, $mail);

                    if ($iNewUID) {
                        Permission::model()->insertSomeRecords(array('uid' => $iNewUID, 'permission' => Yii::app()->getConfig("defaulttemplate"), 'entity' => 'template', 'read_p' => 1));

                        // Set permissions: Label Sets 
                        $auto_create_labelsets = $this->get('auto_create_labelsets', null, null, true);
                        if ($auto_create_labelsets) {

                            Permission::model()->insertSomeRecords(array('uid' => $iNewUID, 'permission' => 'labelsets', 'entity' => 'global', 'create_p' => 1, 'read_p' => 1, 'update_p' => 1, 'delete_p' => 1, 'import_p' => 1, 'export_p' => 1));
                        }

                        // Set permissions: Particiapnt Panel 
                        $auto_create_participant_panel = $this->get('auto_create_participant_panel', null, null, true);
                        if ($auto_create_participant_panel) {

                            Permission::model()->insertSomeRecords(array('uid' => $iNewUID, 'permission' => 'participantpanel', 'entity' => 'global', 'create_p' => 1, 'read_p' => 0, 'update_p' => 0, 'delete_p' => 0, 'export_p' => 0));
                        }

                        // Set permissions: Settings & Plugins 
                        $auto_create_settings_plugins = $this->get('auto_create_settings_plugins', null, null, true);
                        if ($auto_create_settings_plugins) {

                            Permission::model()->insertSomeRecords(array('uid' => $iNewUID, 'permission' => 'settings', 'entity' => 'global', 'create_p' => 0, 'read_p' => 1, 'update_p' => 1, 'delete_p' => 0, 'import_p' => 1, 'export_p' => 0));
                        }

                        // Set permissions: surveys 
                        $auto_create_surveys = $this->get('auto_create_surveys', null, null, true);
                        if ($auto_create_surveys) {

                            Permission::model()->insertSomeRecords(array('uid' => $iNewUID, 'permission' => 'surveys', 'entity' => 'global', 'create_p' => 1, 'read_p' => 0, 'update_p' => 0, 'delete_p' => 0, 'export_p' => 0));
                        }

                        // Set permissions: Templates 
                        $auto_create_templates = $this->get('auto_create_templates', null, null, true);
                        if ($auto_create_templates) {

                            Permission::model()->insertSomeRecords(array('uid' => $iNewUID, 'permission' => 'templates', 'entity' => 'global', 'create_p' => 1, 'read_p' => 1, 'update_p' => 1, 'delete_p' => 1, 'import_p' => 1, 'export_p' => 1));
                        }

                        // Set permissions: User Groups 
                        $auto_create_user_groups = $this->get('auto_create_user_groups', null, null, true);
                        if ($auto_create_user_groups) {

                            Permission::model()->insertSomeRecords(array('uid' => $iNewUID, 'permission' => 'usergroups', 'entity' => 'global', 'create_p' => 1, 'read_p' => 1, 'update_p' => 1, 'delete_p' => 1, 'export_p' => 0));
                        }

                        // read again user from newly created entry
                        $oUser = $this->api->getUserByName($sUser);

                        $this->setAuthSuccess($oUser);
                    } else {
                        $this->setAuthFailure(self::ERROR_USERNAME_INVALID);
                    }
                } else {
                    $this->setAuthFailure(self::ERROR_USERNAME_INVALID);
                }
            } else {
                // Update user?
                $auto_update_users = $this->get('auto_update_users', null, null, true);
                if ($auto_update_users) {
                    $changes = array(
                        'full_name' => $name,
                        'email' => $mail,
                    );

                    User::model()->updateByPk($oUser->uid, $changes);


                    $oUser = $this->api->getUserByName($sUser);
                }

                $this->setAuthSuccess($oUser);
            }
        }
    }

}