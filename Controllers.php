<?php
/**
 * Created by PhpStorm.
 * User: bada
 * Date: 02.12.2016
 * Time: 15:16
 */
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Finder\Finder;
///////////////////////////////////////////////////////admin page////////////////////////////////////////////////////////
$app->match('/admin', function (Request $request) use ($app){

    $form = $app['form.factory']
        ->createBuilder('form')
        ->getForm();
    $message = 'Hello admin '.$app['session']->get('user')["username"].'. Choose what to do.';
   // if ($app['dbs']==NULL) {
     //   $message = 'input this data before continue please';
    //}
    $response = $app['twig']->render(
        'index.html.twig',
        array(
            'title' => 'Admin page',
            'message' => $message,
            'formDataIns' => $form->createView()
        ));
    return $response;
}, 'GET|POST')->before(function() use($app) {
    if ($app['session']->get('user') == NULL) {
        return $app->redirect($app['url_generator']->generate('login'));
    }
});
///////////////////////////////////////////////////////new page builder/////////////////////////////////////////////////
$app->match('/admin/newpage', function (Request $request) use ($app){
    $finder = new Finder();
    $finder->directories()->in(__DIR__.'\templates');
    $found=array();
    foreach ($finder as $file) {
        $found[$file->getRelativePathname()]=$file->getRelativePathname();
    }
    $form = $app['form.factory']
        ->createBuilder('form')
        ->add('PageName','text',array( 'max_length'=>'100', 'required' => true))
        ->add('SelectTemplate','choice',array(
            'choices' => $found,
            'expanded' => false))
        ->add('FileUpload', 'file')
        ->add('PageContent','textarea',array(/*textarea options here*/))
        ->getForm();
    $request = $app['request'];
    $message = 'Hello admin '.$app['session']->get('user')["username"].'. You are welcome to create new page';
    if ($request->isMethod('POST')) {
        $form->bind($request);
        if ($form->isValid()) {
            $formInput=$request->get($form->getName());
            $pageName = $formInput['PageName'];
            $template= $formInput['SelectTemplate'];
            $pageContent= $formInput['PageContent'];
            $files = $request->files->get($form->getName());
            /* Make sure that Upload Directory is properly configured and writable */
            $path = __DIR__.'/../web/upload/'.$pageName;
            $filename = $files['FileUpload']->getClientOriginalName();
            $files['FileUpload']->move($path,$filename);

            $app['dbs']['adminUser']->insert('pages', array(
                'name' => $pageName,
                'template' => $template,
                'content' => $pageContent,
                'image' => str_replace(" ","%20",$pageName).'/'.$filename
            ));
            $message = 'Page was successfully created!';
        }
    }
    $response =  $app['twig']->render(
        'index.html.twig',
        array(
            'title' => 'Create new page',
            'message' => $message,
            'formDataIns' => $form->createView()
        )
    );
    return $response;
}, 'GET|POST')->bind("newPage")->before(function() use($app) {
    if ($app['session']->get('user') == NULL) {
        return $app->redirect($app['url_generator']->generate('login'));
    }
});
$app->error(function (\Exception $e, $code) use ($app) {
    $response = null;
    if (! $app['debug']) {
        switch ($code) {
            case 404:
                $message = 'The requested page could not be found.';
                break;
            default:
                $message = 'We are sorry, but something went terribly wrong.';
        }
        $response = new Response($message, $code);
    }
    return $response;
});
//////////////////////////////////////////// showing info from database page ///////////////////////////////////////////
$app->get('/admin/showdb', function() use($app) {
    $dbout=$app['dbs']['adminUser']->fetchALL('SELECT * FROM pages');
    return $app['twig']->render('showdb.html.twig', array(
        'dbout' =>$dbout,
        'go' => var_dump($app['dbs']['adminUser']->fetchALL("SELECT USER(),CURRENT_USER()"))
    ));
})->bind("showdb")->before(function() use($app) {
    if ($app['session']->get('user') == NULL) {
        return $app->redirect($app['url_generator']->generate('login'));
    }
});
/////////////////////////////////////////////////// search info by id //////////////////////////////////////////////////
$app->get('/admin/showdb/by_id/{id}', function($id) use($app) {
    $sql = "SELECT * FROM pages WHERE id= ?";
    $dbout = $app['dbs']['adminUser']->fetchALL($sql,$id);
    return $app['twig']->render('showdb.html.twig', array(
        'dbout' =>$dbout
    ));
})->before(function() use($app) {
    if ($app['session']->get('user') == NULL) {
        return $app->redirect($app['url_generator']->generate('login'));
    }
});
////////////////////////////////////////////// register  admin page ////////////////////////////////////////////////////
    $app->match('/admin/register', function (Request $request) use ($app) {
        $form = $app['form.factory']
            ->createBuilder('form')
            ->add('Username', 'text', array('max_length' => '100', 'required' => true))
            ->add('Password', 'password', array('required' => true))
            ->add('ConfirmPassword', 'password', array('required' => true))
            ->add('Permissions', 'choice', array(
                'choices' => array(1=>'full', 2=>'create pages'),
                'expanded' => false
            ))
            ->getForm();
        $message = 'Hello admin ' . $app['session']->get('user')["username"] . '. You can create new admin user.';
        $request = $app['request'];
        if ($request->isMethod('POST')) {
            $form->bind($request);
            if ($form->isValid()) {
                $formInput = $request->get($form->getName());

                $sql = "SELECT admin_name FROM admin_user WHERE admin_name = ?";
                if ($app['dbs']['adminUser']->fetchALL($sql,$formInput['Username'])==false){
                    if ($formInput['Password'] != $formInput['ConfirmPassword']) {
                        $message = 'password does not match';
                    } else {
                        $newUserName = $formInput['Username'];
                        $newUserPassword = $formInput['Password'];
                        $newUserPermissions = $formInput['Permissions'];
                        $app['dbs']['adminUser']->insert('admin_user', array(
                            'admin_name' => $newUserName,
                            'password' => $newUserPassword,
                            'Permissions' => $newUserPermissions
                        ));
                        $message = 'New admin user was successfully added!';
                    }
                } else {$message = 'Admin user with such name already exists!';}
            }
        }
        $response = $app['twig']->render(
            'index.html.twig',
            array(
                'title' => 'Add new admin',
                'message' => $message,
                'formDataIns' => $form->createView()
            )
        );
        return $response;
    }, 'GET|POST')->bind("regAdmin")->before(function() use($app) {
        if ($app['session']->get('user') == NULL) {
            return $app->redirect($app['url_generator']->generate('login'));
        }
    });/////must be able only from other admin
/////////////////////////////////////////////// login page /////////////////////////////////////////////////////////////
    $app->get('/login', function () use ($app) {
        $username = $app['request']->server->get('PHP_AUTH_USER', false);
        $password = $app['request']->server->get('PHP_AUTH_PW');
        $sql = "SELECT admin_name,password FROM admin_user WHERE admin_name = ?";
        $flag=$app['dbs']['adminUser']->fetchALL($sql, $username);
        if ($flag==true && $flag['0']['admin_name'] === $username && $flag['0']['password'] === $password) {
            $app['session']->set('user', array('username' => $username));
            return $app->redirect('./admin');
        }
        $response = new Response();
        $response->headers->set('WWW-Authenticate', sprintf('Basic realm="%s"', 'site_login'));
        $response->setStatusCode(401, 'Please sign in.');
        return $response;
    })->bind('login');
/////////////////////////////////////////////// register  user page ////////////////////////////////////////////////////
    $app->match('/register', function (Request $request) use ($app) {
        $form = $app['form.factory']
            ->createBuilder('form')
            ->add('Username', 'text', array('max_length' => '100', 'required' => true))
            ->add('Password', 'password', array('required' => true))
            ->add('ConfirmPassword', 'password', array('required' => true))
            ->add('Email', 'text', array('required' => true))
            ->add('Phone', 'integer', array('required' => true))
            ->getForm();
        $message = 'Input some info about you, please';
        if ($request->isMethod('POST')) {
            $form->bind($request);
            if ($form->isValid()) {
                $formInput = $request->get($form->getName());
                $sql = "SELECT user_name FROM users WHERE user_name = ?";
                if ($app['dbs']['adminUser']->fetchALL($sql, $formInput['Username'])==false){
                    if ($formInput['Password'] != $formInput['ConfirmPassword']) {
                        $message = 'password does not match';
                    } else {
                        $newUserName = $formInput['Username'];
                        $newUserPassword = $formInput['Password'];
                        $newUserEmail = $formInput['Email'];
                        $newUserPhone = $formInput['Phone'];
                        $app['dbs']['adminUser']->insert('users', array(
                            'user_name' => $newUserName,
                            'password' => $newUserPassword,
                            'email' => $newUserEmail,
                            'phone' => $newUserPhone,
                            'status' => 'On'
                        ));
                        $message = 'Thank you for registration!';
                    }
                } else {$message = 'User with such name already exists!';}
            }
        }



        $request = $app['request'];
        $response = $app['twig']->render(
            'index.html.twig',
            array(
                'title' => 'Registering new user',
                'message' => $message,
                'formDataIns' => $form->createView()
            )
        );
        return $response;
    }, 'GET|POST');
/////////////////////////////////////////////// hello your name ////////////////////////////////////////////////////////
    $app->get('/account', function () use ($app) {
        if (null === $user = $app['session']->get('user')) {
            return $app->redirect('/login');
        }

        return "Welcome {$user['username']}!";
    });
/////////////////////////////////////////////////test///////////////////////////////////////////////////////////////////
$app->match('/test', function (Request $request) use ($app) {
    $subFormBuilder = $app['form.factory']->createBuilder(
        null /* default data */,
        ['label' => 'Sub Form'] /* options */
    )
        ->add('name');
    $form = $app['form.factory']->createBuilder()
        ->add($subFormBuilder)
        ->add('number')
        ->getForm();



    $message = 'menu';
    $request = $app['request'];
    if ($request->isMethod('POST')) {
        $form->bind($request);
        if ($form->isValid()) {
            $formInput = $request->get($form->getName());
            $menu = $formInput['Menu'];
            $subMenu = $formInput['Submenu'];
        }
    }

    $response = $app['twig']->render(
        'index.html.twig',
        array(
            'title' => 'Add new admin',
            'message' => $message,
            'formDataIns' => $form->createView()
        )
    );
    return $response;
}, 'GET|POST');
///////////////////////////////////////////// page generator ///////////////////////////////////////////////////////////
    $app->get('/{pageName}', function ($pageName) use ($app) {
        $sql = "SELECT * FROM pages WHERE name= ?";
        $dbout = $app['dbs']['anyUser']->fetchALL($sql,$pageName);
        if ($dbout == !null) {
            $templateName = $dbout[0]['template'];
            $renderIn = "\\..\\src\\templates\\$templateName";
            $app['twig.loader.filesystem']->addPath(__DIR__ . $renderIn);
            return $app['twig']->render($templateName . '.html.twig', array(
                'dbout' => $dbout[0]
            ));
        } else {
            return $app->abort(404);
        }
    });
