{pkgs}: {
  deps = [
    pkgs.systemdLibs
    pkgs.libgbm
    pkgs.xorg.libxcb
    pkgs.xorg.libXrandr
    pkgs.xorg.libXfixes
    pkgs.xorg.libXext
    pkgs.xorg.libXdamage
    pkgs.xorg.libXcomposite
    pkgs.xorg.libX11
    pkgs.alsa-lib
    pkgs.cairo
    pkgs.pango
    pkgs.libxkbcommon
    pkgs.mesa
    pkgs.libdrm
    pkgs.expat
    pkgs.cups
    pkgs.dbus
    pkgs.at-spi2-atk
    pkgs.atk
    pkgs.nspr
    pkgs.nss
    pkgs.glib
  ];
}
