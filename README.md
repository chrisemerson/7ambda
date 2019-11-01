# 7ambda

7ambda is (or will be) a tool to generate layer code for AWS Lambda that enables use of PSR-7 and PSR-15 objects to
interact with the Lambda infrastructure below. This means functions can be written in a way that can be reused in any
PSR-7 supporting environment, supported by many different frameworks. This is for use with Lambdas that sit behind
API Gateway - Lambda functions triggered by other methods do not make sense in a PSR-7 context, so are not supported.

This project is very much in 'proof of concept' phase currently. The layer code must be created manually and the tool
does not yet exist, but the layer proves the concept that PSR-7 and PSR-15 interfaces can be used in AWS Lambda.

## Generating PHP Runtime Binary

The PHP runtime is generated using an EC2 instance based on the Lambda runtime environment, to ensure compatibility.
First, look up the AMI used in the documentation here:

https://docs.aws.amazon.com/lambda/latest/dg/current-supported-versions.html

At time of writing, the custom runtime environment appears to be based on Amazon Linux 1, with AMI
`amzn-ami-hvm-2018.03.0.20181129-x86_64-gp2`, `ami-08935252a36e25f85`. Create an instance based on this that you can SSH
in to.

Update packages to the latest version, and install the following additional packages:

    sudo yum update -y
    sudo yum install -y autoconf bison gcc gcc-c++ libcurl-devel libxml2-devel

If you require SSL support (almost certainly), also install `openssl-devel`

If you require the GD extension, also install `freetype-devel`, `libpng-devel`, `libjpeg-devel`, `libXpm-devel` and
`libwebp-devel`

Other extensions may required other packages, but the PHP compilation will let you know if something is missing. Next,
create a directory for PHP to be installed into:

`mkdir ~/php-7-bin`

Download the PHP source for the version you want to use and unzip:

`curl -sL https://github.com/php/php-src/archive/php-{PHP_VERSION}.tar.gz | tar -xvz`

Build PHP:

    cd php-src-php-{PHP_VERSION}
    ./buildconf --force
    
The configure line will depend on which extensions you want to use or enable, but it's important to include the
`--enable-static=yes` and `--enable-shared=no` options to ensure extensions are compiled into the runtime itself. The
`--disable-all` flag allows you to whitelist which extensions get built into the binary. Documentation on php.net can
help you figure out which flags and packages you need for different extensions.

    ./configure  --prefix=/home/ec2-user/php-7-bin/ --enable-static=yes --enable-shared=no --disable-all --enable-hash --enable-json --enable-libxml --enable-mbstring --enable-phar --enable-soap --enable-xml --with-curl --with-gd --with-zlib --with-openssl --without-pear --enable-ctype --enable-filter --enable-simplexml
    make install

Finally, copy the runtime (`/home/ec2-user/php-7-bin/bin/php`) down from the machine and put into the layer folder in
this project. Run `composer install` inside the layer folder, ensuring that the version of PHP used to install packages
matches that of the runtime you created (You can add platform config into the composer.json file to instruct it on which
version to assume). Finally, `zip` up the layer folder, ensuring the `bootstrap` file has executable permissions, and the
following files are included:

  - bootstrap
  - php
  - runtime.php
  - entire src/ folder
  - entire vendor/ folder

## Usage

In AWS Lambda, create a new layer. Give it a name, and use the zip file you created above as the source. Copy the Layer
ARN for use later. If you need to re-upload the layer code after some changes, use the 'Create New Version' function
instead - though the ARN will still need to be updated.

Now you can create an AWS Lambda function, and specify the ARN as the layer that is used. The layer requires that the
Lambda has an `index.php` file which is automatically included. You can put whatever you want in here (all your Lambda
code if it's simple and small enough), but it is suggested that you use this to include or set up your auto-loading
code. Finally, the `Handler` property in AWS Lambda should be set to the fully-qualified class name of a class that
implements PSR-15s `RequestHandlerInterface`. This interface defines a single method, `handle`, that takes a PSR-7
Request object as its only argument, and returns a PSR-7 Response object. The layer code above will construct the PSR-7
Request using Zend Diactoros and pass it to this method, and it will decode the Response object back into a Lambda
response for you.

It is intended that future versions of this layer code will remove the reliance on Zend Diactoros (see below).

## Known Issues

Currently Zend Diactoros is used in the project to provide the PSR-7 request that is passed to the Lambda. Because of
this, if you were to use Diactoros in the Lambda code you run, it would be locked to the version that is used in the
layer - currently v2.1.latest.

The layer code also uses the `psr/http-message` and `psr/http-server-handler` packages, though because these are
abstract code packages and considered stable at v1.0 with no further releases expected, this is less of an issue.
