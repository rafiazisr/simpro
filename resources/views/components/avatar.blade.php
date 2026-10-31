@props(['user', 'size' => null])
<span {{ $attributes->merge(['class' => trim('av '.($size === 'sm' ? 'av-sm ' : '').$user->avatarColor())]) }} title="{{ $user->name }}">{{ $user->initials() }}</span>
