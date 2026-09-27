#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
打包脚本 —— 生成可直接解压到 public/plugins/sms/ 的发行 zip。

用法：
    python build.py                # 输出 dist/AliyunPnvs-<version>.zip
    python build.py --out <DIR>    # 指定输出目录

版本号自动从 AliyunPnvs.php 的 $info['version'] 读取，无需手工同步。
"""

import os
import re
import sys
import zipfile

# V10 通过目录名 parse_name($dir, 1) 推导类名，目录名必须是 aliyun_pnvs，不能改
PLUGIN_DIR_NAME = 'aliyun_pnvs'

ROOT = os.path.dirname(os.path.abspath(__file__))
MAIN_CLASS = os.path.join(ROOT, 'AliyunPnvs.php')

# 打进发行包的内容；未列出的（.git、dist、build.py 等）不进包
INCLUDE_FILES = [
    'AliyunPnvs.php',
    'config.php',
    'README.md',
    'CHANGELOG.md',
    'LICENSE',          # GPL-3.0 要求分发时附带许可证全文
]
INCLUDE_DIRS = [
    'config',
]


def read_version():
    """从主类的 $info['version'] 读取版本号。"""
    src = open(MAIN_CLASS, encoding='utf-8').read()
    m = re.search(r"'version'\s*=>\s*'([^']+)'", src)
    if not m:
        raise SystemExit("无法从 AliyunPnvs.php 读取 $info['version']")
    return m.group(1)


def collect():
    """收集待打包文件，返回绝对路径列表。"""
    items = []
    for name in INCLUDE_FILES:
        p = os.path.join(ROOT, name)
        if os.path.isfile(p):
            items.append(p)
        else:
            print('  跳过（不存在）: %s' % name)
    for d in INCLUDE_DIRS:
        for cur, _dirs, names in os.walk(os.path.join(ROOT, d)):
            for n in names:
                items.append(os.path.join(cur, n))
    return sorted(items)


def main():
    version = read_version()

    out_dir = os.path.join(ROOT, 'dist')
    if '--out' in sys.argv:
        idx = sys.argv.index('--out')
        if idx + 1 >= len(sys.argv):
            raise SystemExit('--out 需要跟一个目录参数')
        out_dir = os.path.abspath(sys.argv[idx + 1])
    os.makedirs(out_dir, exist_ok=True)

    zip_path = os.path.join(out_dir, 'AliyunPnvs-%s.zip' % version)
    if os.path.exists(zip_path):
        os.remove(zip_path)

    files = collect()

    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as z:
        for p in files:
            rel = os.path.relpath(p, ROOT).replace(os.sep, '/')
            z.write(p, '%s/%s' % (PLUGIN_DIR_NAME, rel))

    print('已生成  : %s' % zip_path)
    print('插件版本: v%s' % version)
    print('安装目录: %s/' % PLUGIN_DIR_NAME)
    print('打包内容:')

    total = 0
    with zipfile.ZipFile(zip_path) as z:
        for i in z.infolist():
            total += i.file_size
            print('  %-48s %7d B' % (i.filename, i.file_size))

    print('  %s' % ('-' * 58))
    print('  %d 个文件 | 解压后 %d B | zip %d B'
          % (len(files), total, os.path.getsize(zip_path)))


if __name__ == '__main__':
    main()
