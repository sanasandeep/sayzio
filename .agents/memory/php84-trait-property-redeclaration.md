---
name: PHP 8.4 trait property redeclaration fatal
description: A class-level property redeclaring a trait's property with a different type or default value throws a fatal "incompatible property definition" at class-load time under PHP 8.3+/8.4.
---

Declaring `public bool $prop = true;` on a class that `use`s a trait which already declares `public $prop;` (untyped, no default) is a **compile-time fatal**, not a warning: "X and Y define the same property ($prop) in the composition of X. However, the definition differs and is considered incompatible." It fires the moment the class is autoloaded/referenced, before any of its code runs.

This bit `Illuminate\Bus\Queueable::$afterCommit` (declared `public $afterCommit;`, no type, no default) vs a queued Job class that redeclared `public bool $afterCommit = true;` to set the flag inline. Every dispatch site for that job crashed on class load.

**Why:** PHP 8.3+ tightened trait/class property composition compatibility checks (type AND default value must match, not just visibility) — code that predates this or was written against looser rules silently breaks when the PHP version bumps.

**How to apply:** Never redeclare a trait-provided property just to set a default. Instead, set it via the trait's own setter method in the constructor (e.g. Laravel's `Queueable` traits expose `$this->afterCommit()`, `$this->onQueue()`, etc.) — search for a same-named method on the trait before assuming you need to redeclare the property. If you hit a similarly-worded fatal ("define the same property... considered incompatible"), grep the trait's source for the property's exact declaration (type + default) and match it exactly, or avoid redeclaring at all.
