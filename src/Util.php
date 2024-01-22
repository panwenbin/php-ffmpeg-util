<?php

namespace Panwenbin\PhpFFmpegUtil;

use FFMpeg\Driver\FFMpegDriver;
use FFMpeg\FFProbe;
use FFMpeg\FFProbe\DataMapping\Stream;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class Util {
    /** @var FFMpegDriver */
    protected $driver;

    public function __construct(FFMpegDriver $driver) {
        $this->driver = $driver;
    }

    /**
    * 视频截图
    * @param string $videoPath 视频路径
    * @param string $imagePath 图片路径
    * @param string $ss 截图开始时间点 比如 00:00:02 或者 2.0
    * @param string $s 图片size 比如 320x240
    * @return bool
     */
    public function vframe(string $videoPath, string $imagePath, string $ss, string $s = '')
    {
        $commands = [
            '-y',
            '-ss', $ss,
            '-i', $videoPath,
            '-vframes', '1',
            '-f', 'image2',
        ];
        if ($s) {
            $commands = array_merge($commands, ['-s', $s]);
        }
        $commands = array_merge($commands, [$imagePath]);

        return $this->driver->command($commands);
    }

    /**
     * 视频截GIF
     * @param string $videoPath 视频路径
     * @param string $gifPath GIF路径
     * @param string $ss 截图开始时间点 比如 00:00:02 或者 2.0
     * @param string $t GIF时长 比如 5.0
     * @param string $s GIFsize 比如 320x240
     * @param int $r GIF帧率
     * @return bool
     */
    public function vframes(string $videoPath, string $gifPath, string $ss, string $t, string $s = '', int $r = 25)
    {
        $commands = [
            '-y',
            '-ss', $ss,
            '-i', $videoPath,
            '-t', $t,
            '-r', $r,
            '-f', 'gif',
        ];
        if ($s) {
            $commands = array_merge($commands, ['-s', $s]);
        }
        $commands = array_merge($commands, [$gifPath]);

        return $this->driver->command($commands);
    }

    /**
     * 文字水印
     * @param string $videoPath 视频路径
     * @param string $outPath 输出视频路径
     * @param string $text 文字水印
     * @param string $font 字体文件路径
     * @param string $fontSize 字体大小
     * @param string $fontColor 字体颜色
     * @param string $x 水印位置x
     * @param string $y 水印位置y
     * @param float $alpha 透明度
     * @param bool $box 是否有边框
     * @param string $boxColor 边框颜色
     * @return bool
     */
    public function drawText(string $videoPath, string $outPath, string $text, string $font, string $fontSize, string $fontColor, string $x, string $y, float $alpha = 1, bool $box = false, string $boxColor = '')
    {
        $vf = "drawtext=fontfile={$font}:text='{$text}':fontsize={$fontSize}:fontcolor={$fontColor}:x={$x}:y={$y}:alpha={$alpha}";
        if ($box) {
            $vf .= ":box={$box}";
            if ($boxColor) {
                $vf .= ":boxcolor={$boxColor}";
            }
        }
        $commands = [
            '-y',
            '-re',
            '-i', $videoPath,
            '-vf', $vf,
            $outPath,
        ];

        return $this->driver->command($commands);
    }

    /**
     * 图片水印
     * @param string $videoPath 视频路径
     * @param string $outPath 输出视频路径
     * @param string $imagePath 图片路径
     * @param string $x 水印位置x
     * @param string $y 水印位置y
     * @return bool
     */
    public function drawImage(string $videoPath, string $outPath, string $imagePath, string $x, string $y)
    {
        $vf = "movie={$imagePath} [logo]; [in][logo] overlay={$x}:{$y} [out]";
        $commands = [
            '-y',
            '-i', $videoPath,
            '-vf', $vf,
            $outPath,
        ];

        return $this->driver->command($commands);
    }

    /**
     * 图片合成视频
     * @param array $images 图片路径数组
     * @param string $outPath 输出视频路径
     * @param string $t 视频时长
     * @param string $fps 帧率
     * @return bool
     * @throws \Exception
     */
    public function imagesToVideo(array $images, string $outPath, string $t, string $fps = '25', $codec = 'libx264', $pixFmt = 'yuv420p')
    {
        $dir = new TemporaryDirectory($this->driver->getConfiguration()->get('temporary_directory') ?: '');
        $fs = $dir->create();
        $ext = '';
        foreach($images as $i => $image) {
            $_ext = pathinfo($image, PATHINFO_EXTENSION);
            if (!$ext) {
                $ext = $_ext;
            } elseif ($ext != $_ext) {
                throw new \Exception('图片格式不一致');
            }
            copy($image, $fs->path(sprintf('%00d', $i).'.'.$ext));
        }
        $commands = [
            '-y',
            '-framerate', count($images)."/${t}",
            '-i', $fs->path('%00d.'.$ext),
            '-r', $fps,
            '-c:v', $codec,
            '-pix_fmt', $pixFmt,
            $outPath,
        ];

        $ret = $this->driver->command($commands);

        foreach($images as $i => $image) {
            unlink($fs->path(sprintf('%00d', $i).'.'.$ext));
        }
        rmdir($fs->path());

        return $ret;
    }

    /**
     * 图片合成视频
     * @param array $images 图片路径数组
     * @param string $outPath 输出视频路径
     * @param string $t 视频时长
     * @param string $fps 帧率
     * @return bool
     * @throws \Exception
     */
    public function concatImagesToVideo(array $images, string $outPath, string $t, string $fps = '25')
    {
        $dir = new TemporaryDirectory($this->driver->getConfiguration()->get('temporary_directory') ?: '');
        $fs = $dir->create();
        $filelist = $fs->path('filelist'.microtime(true).'.txt');
        file_put_contents($filelist, implode("\n", array_map(function ($image) use ($t, $images) {
            return "file '".realpath($image)."'\nduration ".round($t/count($images),2);
        }, $images)));
        $commands = [
            '-y',
            '-f', 'concat',
            '-safe', '0',
            '-i', $filelist,
            '-r', $fps,
            $outPath,
        ];

        $ret = $this->driver->command($commands);

        unlink($filelist);
        rmdir($fs->path());

        return $ret;
    }

    /**
     * 图片循环合成视频
     * @param array $images 图片路径数组
     * @param string $outPath 输出视频路径
     * @param float $t 视频时长
     * @param string $size 视频尺寸
     * @param string $sar 视频比例
     * @param string $codec 视频编码
     * @param string $pixFmt 视频像素格式
     * @param string $frameRate 视频帧率
     * @return bool
     * @throws \Exception
     */
    public function loopImagesToVideo(array $images, string $outPath, float $t, string $size = '1920x1080', string $sar = '1/1', string $codec = 'libx264', string $pixFmt = 'yuv420p', string $frameRate = '25')
    {
        $commands = [
            '-y',
        ];
        $perT = round($t/count($images), 2);
        foreach($images as $image) {
            $commands = array_merge($commands, [
                '-loop', 1, '-t', $perT, '-i', $image,
            ]);
        }
        $commands = array_merge($commands, [
            '-codec', $codec,
            '-pix_fmt', $pixFmt,
            '-r', $frameRate,
            '-vf', 'scale='.$size.',setsar='.$sar,
            $outPath,
        ]);
        $ret = $this->driver->command($commands);
    }

    /**
     * 视频合成视频 相同编码
     * @param array $videos 视频路径数组
     * @param string $outPath 输出视频路径
     * @return bool
     * @throws \Exception
     */
    public function concatVideosSameCodec(array $videos, string $outPath)
    {
        $dir = new TemporaryDirectory($this->driver->getConfiguration()->get('temporary_directory') ?: '');
        $fs = $dir->create();
        $filelist = $fs->path('filelist'.microtime(true).'.txt');
        file_put_contents($filelist, implode("\n", array_map(function ($video) {
            return "file '".realpath($video)."'";
        }, $videos)));
        $commands = [
            '-y',
            '-f', 'concat',
            '-safe', '0',
            '-i', $filelist,
            '-c', 'copy',
            $outPath,
        ];

        $ret = $this->driver->command($commands);

        unlink($filelist);
        rmdir($fs->path());

        return $ret;
    }

    /**
     * 视频合成视频 不同编码, 但是相同stream数量和类型, 并且相同dimensions和sar
     * 兼容没有音频的视频
     * @param array $videos 视频路径数组
     * @param string $outPath 输出视频路径
     * @return bool
     * @throws \Exception
     */
    public function concatVideosSameButCodec(array $videos, string $outPath)
    {
        $commands = [
            '-y',
        ];
        $filters = [];
        $firstSize = "";

        $probe = FFProbe::create();
        $firstSize = "";
        $sar = "";
        $frameRate = "";
        $channelLayout = "";
        $sampleRate = "";
        foreach($videos as $i => $video) {
            $hasVideo = false;
            $hasAudio = false;
            /** @var []Stream $streams */
            $streams = $probe->streams($video);
            foreach($streams as $stream) {
                /** @var \FFMpeg\FFProbe\DataMapping\Stream $stream */
                if ($stream->isVideo()) {
                    $hasVideo = true;
                    $firstSize = $firstSize ?: $stream->get('width').'x'.$stream->get('height');
                    $sar = $sar ?: str_replace(':','/',$stream->get('sample_aspect_ratio'));
                    $frameRate = $frameRate ?: $stream->get('r_frame_rate');
                }
                if ($stream->isAudio()) {
                    $hasAudio = true;
                    $channelLayout = $channelLayout ?: $stream->get('channel_layout');
                    $sampleRate = $sampleRate ?: $stream->get('sample_rate');
                }
            }
            $commands = array_merge($commands, [
                '-i', $video,
            ]);
            $a = $hasAudio ? $i : count($videos);
            $filters[] = "[${i}:v][${a}:a]";
        }

        $commands = array_merge($commands, [
            '-f', 'lavfi', '-t', '0.1', '-i', 'anullsrc=channel_layout='.$channelLayout.':sample_rate='.$sampleRate,
        ]);

        $commands = array_merge($commands, [
            '-filter_complex', implode('', $filters).' concat=n='.count($videos).':v=1:a=1 [v] [a]',
            '-map', '[v]',
            '-map', '[a]',
            $outPath,
        ]);

        $ret = $this->driver->command($commands);
        return $ret;
    }

    /**
     * 追加图片到视频结尾
     * @param string $video 视频路径
     * @param string $image 图片路径
     * @param int $t 图片持续时间
     * @param string $outPath 输出视频路径
     * @return bool
     * @throws \Exception
     */
    public function appendImageToVideo(string $video, string $image, int $t, string $outPath)
    {
        $dir = new TemporaryDirectory($this->driver->getConfiguration()->get('temporary_directory') ?: '');
        $fs = $dir->create();

        $sourceSuffix = pathinfo($video, PATHINFO_EXTENSION);
        $targetSuffix = pathinfo($outPath, PATHINFO_EXTENSION);

        $probe = FFProbe::create();
        $streams = $probe->streams($video);
        $firstCodecName = "";
        $firstSize = "";
        $sar = "";
        $frameRate = "";
        $channelLayout = "";
        $sampleRate = "";
        $pixFmt = "";
        foreach($streams as $stream) {
            /** @var \FFMpeg\FFProbe\DataMapping\Stream $stream */
            if ($stream->isVideo()) {
                $firstCodecName = $firstCodecName ?: $stream->get('codec_name');
                $firstSize = $firstSize ?: $stream->get('width').'x'.$stream->get('height');
                $sar = $sar ?: str_replace(':','/',$stream->get('sample_aspect_ratio'));
                $frameRate = $frameRate ?: $stream->get('r_frame_rate');
                $pixFmt = $pixFmt ?: $stream->get('pix_fmt');
            }
            if ($stream->isAudio()) {
                $channelLayout = $channelLayout ?: $stream->get('channel_layout');
                $sampleRate = $sampleRate ?: $stream->get('sample_rate');
            }
        }

        $firstVideo = $video;
        $imgVideo = $fs->path('tmp.'.$sourceSuffix);

        $this->loopImagesToVideo([$image], $imgVideo, $t, $firstSize, $sar, $firstCodecName, $pixFmt, $frameRate);

        if ($sourceSuffix == $targetSuffix) {
            $ret = $this->concatVideosSameCodec([$firstVideo, $imgVideo], $outPath);
        } else {
            $ret = $this->concatVideosSameButCodec([$firstVideo, $imgVideo], $outPath);
        }

        unlink($imgVideo);
        rmdir($fs->path());

        return $ret;
    }
}
