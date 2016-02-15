limesurvey-saml (customização)
==============================

Modificação do plugin de autenticação SAML para o limesurvey (limesurvey-saml) que adiciona a autenticação também no acesso ao questionário. Customização baseada no plugin [LS-replaceRegister](https://github.com/Shnoulle/LS-replaceRegister).

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
* [Como instalar e configurar o simpleSAMLphp](https://github.com/Frankniesten/limesurvey-saml#how-install-and-configure-simplesamlphp-as-sp)
* [Como instalar e habilitar o plugin limesurvey SAML](https://github.com/Frankniesten/limesurvey-saml#how-install-and-enable-the-saml-plugin)
* [Como configurar os atributos do IdP](https://github.com/hotcyv/limesurvey-saml#como-configurar-os-atributos-do-idp)

Como configurar os atributos do IdP
====================
Inicialmente, o plugin possibilita a utilização da autenticação SAML no acesso ao questionário pelos respondentes, redirecionando-os ao IdP. 

No entanto, é possível requerer também que, além da autenticação, o usuário possua determinado(s) atributo(s) retornados pelo IdP. Assim, inicialmente, deve-se informar na configuração do plugin os atributos providos pelo IdP que ficarão disponíveis ao proprietário do questionário para aplicação do filtro.

Os atributos são configurados via editor json, respeitando a seguinte estrutura em árvore de objetos:

    idpAtributtes {1}
      idpAtributte1 {2}
        label : Descrição do atributo1 que será apresentado ao proprietário do questionário
        options {2}
          value1 : Valor 1
          value2 : Valor 2
      idpAtributte2 {2}
        label : Descrição do atributo2 que será apresentado ao proprietário do questionário
        options {2}
          value1 : Valor 1
          value2 : Valor 2
      ...

Assim, caso deseje-se permitir apenas que usuários de um determinado segmento tenha acesso ao questionário, poderia-se configurar o plugin da seguinte forma: 

    idpAtributtes {1}
      eduPersonAffiliation {2}
        label : Restringir acesso a um segmento?
        options {4}
          none : Não
          student : Discente
          faculty : Docente
          employee : Técnico Adminstrativo
      

