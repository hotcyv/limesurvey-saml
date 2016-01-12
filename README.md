limesurvey-saml (customizado)
==============================

Customização do plugin de autenticação SAML para o limesurvey (limesurvey-saml) que adiciona a autenticação também no acesso ao questionário. Customização baseada no plugin [LS-replaceRegister](https://github.com/Shnoulle/LS-replaceRegister).

Plugin Limesurvey-saml: [Frankniesten/limesurvey-saml](https://github.com/Frankniesten/limesurvey-saml)


Autor
------

Victor Gonçalves <hotcyv@gmail.com>


Licença
-------

GPL2 http://www.gnu.org/licenses/gpl.html


Documentação:
====================

Basicamente, a mesma documentação do plugin original:
* [Como instalar e configurar o simpleSAMLphp](https://github.com/hotcyv/limesurvey-saml#how-install-and-configure-simplesamlphp-as-sp)
* [Como instalar e habilitar o plugin limesurvey SAML](https://github.com/hotcyv/limesurvey-saml#how-install-and-enable-the-saml-plugin)
* Como configurar os atributos do IdP

Como configurar os atributos do IdP
====================
Inicialmente, o plugin possibilita a utilização da autenticação SAML no acesso ao questionário pelos respondentes, redirecionando-os ao IdP.

No entanto, é possível requerer também que, além da autenticação, o usuário possua determinado(s) atributo(s) retornados pelo IdP.

Assim, inicialmente, deve-se informar na configuração do plugin os atributos providos pelo IdP e que ficarão disponíveis ao proprietário do questionário para aplicação do filtro.