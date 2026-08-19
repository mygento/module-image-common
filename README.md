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

```xml
<virtualType name="Xxx\Bbbb\Model\BannerResizer" type="Mygento\ImageCommon\Model\Resizer">
    <arguments>
        <argument name="srcPath" xsi:type="string">xxx/banner</argument>
        <argument name="outputPath" xsi:type="string">xxx/banner/cache</argument>
    </arguments>
</virtualType>
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

```xml
<virtualType name="Xxx\Bbb\Model\Uploader" type="Mygento\ImageCommon\Model\Uploader">
    <arguments>
        <argument name="baseTmpPath" xsi:type="string">xxx/tmp/banner</argument>
        <argument name="basePath" xsi:type="string">xxx/banner</argument>
        <argument name="allowedExtensions" xsi:type="array">
            <item name="jpg" xsi:type="string">jpg</item>
            <item name="jpeg" xsi:type="string">jpeg</item>
            <item name="png" xsi:type="string">png</item>
        </argument>
        <argument name="allowedMimeTypes" xsi:type="array">
            <item name="jpg" xsi:type="string">image/jpg</item>
            <item name="jpeg" xsi:type="string">image/jpeg</item>
            <item name="png" xsi:type="string">image/png</item>
        </argument>
    </arguments>
</virtualType>
```