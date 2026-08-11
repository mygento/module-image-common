# Magento 2 Share Image Resizer Library and Custom Entity Uploader

A reusable Magento 2 library for **image resizing** and **custom entity image uploading**.

This module provides common utilities that can be shared across Magento 2 modules, helping developers avoid duplicating image-processing and image-upload logic for different custom entities.

## Features

* **Image Resizer**

  * Resize images to custom dimensions.
  * Convert images to modern formats
  * Optimize images

* **Custom Entity Uploader**

  * Upload images with 2 folder structure
  * Reduce duplicated uploader implementations across modules.

## Installation

Install the package using Composer:

```bash
composer require mygento/module-image-common
```

## Image Resizer

The image resizer provides a common service for processing and resizing images.

Example usage:

```php
TODO:
```

The actual interface and method signature may vary depending on the implementation.

## Custom Entity Uploader

The custom entity uploader can be used when an entity needs to store an uploaded image.

For example, a custom entity such as:

* Brand
* Manufacturer
* Category extension
* Product extension
* Banner
* Store locator
* Custom CMS entity

can use the shared uploader rather than implementing its own upload logic.

Example:

```php
TODO:
```