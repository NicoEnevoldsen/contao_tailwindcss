# Contao Tailwind CSS Bundle
**ne-dev/contao-tailwind-bundle**

Ein Tailwind-Bundle für **Contao 5**, das **ohne Node, ohne npm & ohne externen Build-Prozess** auskommt.  
CSS wird automatisch generiert – installieren, einrichten, losarbeiten.

Ideal für Projekte, die direkt Tailwind nutzen möchten, ohne ein eigenes Build-Setup zu pflegen.

---

## 🚀 Features

| Feature                                                                 | Status |
|-------------------------------------------------------------------------|:------:|
| Kompatibel mit Contao 5.3                                               | ✔ |
| kein npm, kein Node notwendig                                           | ✔ |
| Tailwind kompiliert automatisch im Bundle                               | ✔ |
| Direkt nutzbar in Page-Layouts, Templates, Artikeln & Content-Elementen | ✔ |
| Templates & Inhaltselemente werden gescannt                             | ✔ |
| nur verwendete Klassen werden generiert                                 | ✔ |
| CSS kann direkt ins Layout eingebunden werden                           | ✔ |

---

## 🎨 Theme-Konfiguration im Backend
Unter Layout → Tailwind CSS können Tailwind-Konfigurationen hinzugefügt werden.<br>
Diese können im Seitenlayout hinzugefügt werden.

| Einstellung | Wirkung                                                                                                                           |
|--------|-----------------------------------------------------------------------------------------------------------------------------------|
| CSS-Inputfile| Basis für den Buildprozess                                                                                                        |
| Basis-Schriftgröße | Bestimmt Rem-Skalierung (1rem = Xpx)                                                                                              |
| Breakpoints | Responsive Stufen wie sm, md, lg frei definierbar                                                                                 |
| Farben | Stehen direkt als Klassen zur Verfügung (bg-abc-100, text-asd-100 …).<br/>Vorhandene Tailwind-Farben können überschrieben werden. |
| Erweiterte Config | Erweitert die Tailwind-Theme-Definition (@theme {})                                                                               |

---

## 📦 Installation

```bash
composer require ne-dev/contao-tailwind-bundle
```