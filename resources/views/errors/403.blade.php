@extends('admin.layouts.error')

@section('title', __('admin.errors.pages.403.title'))
@section('heading', __('admin.errors.pages.403.title'))
@section('message', isset($exception) && $exception->getMessage() === __('admin.errors.no_admin_access') ? $exception->getMessage() : __('admin.errors.pages.403.body'))
@section('illustration', '404')
