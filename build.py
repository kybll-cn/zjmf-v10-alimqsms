#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
打包脚本 —— 生成可直接解压到 public/plugins/sms/ 的发行 zip。

用法：
    python build.py                # 输出 dist/AliyunPnvs-<version>.zip
    python build.py --out <DIR>    # 指定输出目录

行为：
- 自动发现主类（根目录下含 `public $info =` 的 PHP 文件）
- 从 $info 读 name / version，版本号只需改主类一处
- 打包前缀取「当前目录名」——V10 要求插件目录名即安装目录名
- zip 内每个条目都带 `<目录名>/` 前缀，用户解压到 public/plugins/sms/ 直接可用
"""

import os
import re
import sys
import zipfile

# 进包的固定文件（相对仓库根，不存在则跳过）；主类单独处理
INCLUDE_FILES = ['config.php', 'README.md', 'CHANGELOG.md', 'LICENSE']
INCLUDE_DIRS = ['config']


def has_main_class(d):
    """目录 d 下是否存在含 `public $info =` 的 PHP 文件。"""
    if not os.path.isdir(d):
        return False
    for n in os.listdir(d):
        p = os.path.join(d, n)
        if n.endswith('.php') and os.path.isfile(p):
            try:
                if re.search(r'public\s+\$info\s*=', open(p, encoding='utf-8').read()):
                    return True
            except OSError:
                pass
    return False


def resolve_root():
    """优先用当前工作目录；不行则退回脚本所在目录。"""
    cwd = os.getcwd()
    if has_main_class(cwd):
        return cwd
    here = os.path.dirname(os.path.abspath(__file__))
    if has_main_class(here):
        return here
    raise SystemExit('未找到插件主类，请在插件根目录（含 `public $info` 的 PHP 文件所在目录）运行本脚本')


ROOT = resolve_root()
# V10 通过目录名 parse_name($dir, 1) 推导类名，目录名必须是 aliyun_pnvs，不能改
PLUGIN_DIR_NAME = os.path.basename(ROOT)


def find_main_class():
    for n in sorted(os.listdir(ROOT)):
        p = os.path.join(ROOT, n)
        if n.endswith('.php') and os.path.isfile(p):
            if re.search(r'public\s+\$info\s*=', open(p, encoding='utf-8').read()):
                return p
    raise SystemExit('未找到含 `public $info` 的主类 PHP 文件')


def read_info(path):
    src = open(path, encoding='utf-8').read()

    def field(k):
        m = re.search(r"'%s'\s*=>\s*'([^']*)'" % k, src)
        return m.group(1) if m else ''

    return field('name'), field('version')


def collect(main_class):
    items = [main_class]
    for name in INCLUDE_FILES:
        p = os.path.join(ROOT, name)
        if os.path.isfile(p):
            items.append(p)
        else:
            print('  跳过（不存在）: %s' % name)
    for d in INCLUDE_DIRS:
        base = os.path.join(ROOT, d)
        if not os.path.isdir(base):
            continue
        for cur, _dirs, names in os.walk(base):
            for n in sorted(names):
                items.append(os.path.join(cur, n))
    return sorted(set(items))


def main():
    main_class = find_main_class()
    class_name, version = read_info(main_class)
    if not version:
        raise SystemExit("主类里没读到 $info['version']")

    out_dir = os.path.join(ROOT, 'dist')
    if '--out' in sys.argv:
        i = sys.argv.index('--out')
        if i + 1 >= len(sys.argv):
            raise SystemExit('--out 需要跟一个目录参数')
        out_dir = os.path.abspath(sys.argv[i + 1])
    os.makedirs(out_dir, exist_ok=True)

    base = class_name or os.path.basename(main_class)[:-4]
    zip_path = os.path.join(out_dir, '%s-%s.zip' % (base, version))
    if os.path.exists(zip_path):
        os.remove(zip_path)

    files = collect(main_class)
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as z:
        for p in files:
            rel = os.path.relpath(p, ROOT).replace(os.sep, '/')
            z.write(p, '%s/%s' % (PLUGIN_DIR_NAME, rel))

    print('主类    : %s' % os.path.relpath(main_class, ROOT).replace(os.sep, '/'))
    print('类名    : %s' % base)
    print('版本    : v%s' % version)
    print('安装目录: %s/' % PLUGIN_DIR_NAME)
    print('已生成  : %s' % zip_path)
    print('内容    :')
    total = 0
    with zipfile.ZipFile(zip_path) as z:
        for it in z.infolist():
            total += it.file_size
            print('  %-46s %7d B' % (it.filename, it.file_size))
    print('  %s' % ('-' * 56))
    print('  %d 个文件 | 解压 %d B | zip %d B'
          % (len(files), total, os.path.getsize(zip_path)))


if __name__ == '__main__':
    main()
