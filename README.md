# Login Spam Protection

Contributors: Takahashi_Fumiki,hametuha  
Tags: google, recaptcha, spam, security  
Requires at least: 5.0  
Requires PHP: 7.2  
Tested up to: 6.0  
Stable Tag: nightly

Add Google reCAPTCHA on WordPress' login form.

## Description

This plugin add [Google reCAPTCHA v3](https://developers.google.com/recaptcha/docs/v3?hl=ja) to your WordPress login screen. This decreases spam user registration.

## Installation

- Search from plugin screen and install.
- You can also get release version from [GitHub](https://github.com/hametuha/login-spam-protection/releases). Upload one zip file via plugin uploader.

2 credentials(site key and secret key) from [Google reCAPTCHA v3](https://www.google.com/recaptcha/admin/) are required.

Go to Setting > Login Security and enter credentials. Now your login screen is safe.

### Development

- Clone this repository.
- Do `comopser install`. If you don't have comopser, intstall it.

If you want to develop locally, type `npm run watch`.
Gulp will watch your changes.

##  Changelog 

### 1.0

* First release