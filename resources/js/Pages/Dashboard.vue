<template>
  <app-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Laravel Vue WebRTC
      </h2>
    </template>

    <audio hidden id="ringtone" src="/skype_phone.mp3" loop></audio>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-5">
            <h1>Users</h1>
            <ul class="flex flex-wrap p-2 justify-center items-center">
              <li
                v-for="row in users"
                v-show="row.id != user.id"
                :key="row.id"
                class="
                  bg-gray-500
                  h-16
                  w-16
                  m-4
                  rounded-full
                  text-white
                  relative
                  cursor-pointer
                  flex
                  justify-center
                  items-center
                "
                @click="startCall(row)"
              >
                <span
                  class="indicator online"
                  v-show="onlineUsers.filter((x) => row.id === x).length"
                ></span>

                <p
                  class="
                    flex
                    justify-center
                    items-center
                    text-center
                    font-bold
                    text-center
                  "
                  style="font-size: 10px"
                >
                  {{ row.name }}
                </p>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <Modal :show="ModelShow">
      <div class="p-5">
        <h2>{{ caller ? caller.name : "" }} is Calling...</h2>

        <div class="flex">
          <button
            @click="answerCall"
            class="
              bg-green-300
              hover:bg-green-400
              text-green-800
              font-bold
              py-2
              px-4
              rounded
              inline-flex
              items-center
              text-white
              m-3
            "
          >
            Answer
          </button>
          <button
            @click="rejectCall"
            class="
              bg-red-300
              hover:bg-red-400
              text-red-800
              font-bold
              py-2
              px-4
              rounded
              inline-flex
              items-center
              text-white
              m-3
            "
          >
            Reject
          </button>
        </div>
      </div>
    </Modal>

    <Modal :show="isCallOn">
      <div class="p-5 target-full-screen flex justify-center items-center">
        <div
          class="flex calling-container relative justify-center items-center"
          @dblclick="isFullScreen = !isFullScreen"
        >
          <video
            id="otherVideo"
            v-show="!message"
            playsinline
            class="relative rounded"
            autoplay
            width="1280"
            height="720"
          ></video>
          <video
            v-show="!message"
            id="myVideo"
            playsinline
            autoplay
            muted
            class="
              bg-green
              z-20
              absolute
              bottom-5
              right-5
              z-20
              rounded-lg
              h-1/5
              cursor-pointer
            "
          ></video>
          <div v-if="message" class="py-2 w-100">
            <p class="p-3">{{ message }}</p>
          </div>
        </div>

        <div class="video-call-actions duration" v-if="!message">
          <h3 class="px-2">{{ msToTime(duration) }}</h3>
        </div>
        <div class="video-call-actions">
          <button
            :disabled="!this.localStream?.getAudioTracks()[0]"
            v-if="!message"
            :class="`video-action-button ${audio ? 'mic-active' : 'mic'} `"
            @click="audio = !audio"
          ></button>

          <button
            :disabled="!this.localStream?.getVideoTracks()[0]"
            v-if="!message"
            :class="`video-action-button ${video ? 'camera-active' : 'camera'}`"
            @click="video = !video"
          ></button>

          <button class="video-action-button endcall" @click="endCall">
            End
          </button>

          <button
            v-if="!message"
            :class="`video-action-button ${
              isFullScreen ? 'full-screen-active' : 'full-screen'
            }`"
            @click="isFullScreen = !isFullScreen"
          ></button>

          <button
            v-if="!message"
            class="video-action-button screen"
            @click="shareScreen"
          ></button>
        </div>
      </div>
    </Modal>
  </app-layout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout";
import Modal from "@/Jetstream/Modal";

export default {
  props: ["users", "user"],

  data() {
    return {
      servers: {
        iceServers: [
          { urls: ["stun:bn-turn1.xirsys.com"] },
          {
            username:
              "LpTQzzNnOhyCNV2c-XzFWdQLfX9rjGUb_8A9FR82F59dG-y2bjDkk8hIxh5aEboqAAAAAGHaoB1rYXZpbg==",
            credential: "2c2be25c-7128-11ec-b2b6-0242ac140004",
            urls: [
              "turn:bn-turn1.xirsys.com:80?transport=udp",
              "turn:bn-turn1.xirsys.com:3478?transport=udp",
              "turn:bn-turn1.xirsys.com:80?transport=tcp",
              "turn:bn-turn1.xirsys.com:3478?transport=tcp",
              "turns:bn-turn1.xirsys.com:443?transport=tcp",
              "turns:bn-turn1.xirsys.com:5349?transport=tcp",
            ],
          },
        ],
      },
      PC: null,
      localStream: null,
      remoteStream: null,
      ModelShow: false,
      isCallOn: false,
      caller: null,
      offerData: null,
      message: null,
      onlineUsers: [],
      audio: true,
      video: true,
      senderTrack: null,
      duration: null,
      interval: null,
      isFullScreen: false,
    };
  },
  components: {
    AppLayout,
    Modal,
  },

  methods: {
    async setupCall() {
      const conState = this.PC.connectionState;
      if (conState === "closed") {
        this.PC = new RTCPeerConnection(this.servers);
      }

      this.localStream = await this.createVideoStream();
      if (this.localStream === null) {
        this.localStream = await this.createVideoStream(true);
        if (this.localStream) {
          alert("No video permission. & switch to audio only");
        }
      }
      if (this.localStream === null) {
        this.localStream = await this.createVideoStream(false, true);
        if (this.localStream) {
          alert("No audio permission. & switch to video only");
        }
      }
      if (this.localStream === null) {
        alert("Video and Audio permission denied");
      }

      this.remoteStream = new MediaStream();

      // Push tracks from local stream to peer connection
      this.localStream.getTracks().forEach((track) => {
        this.senderTrack = this.PC.addTrack(track, this.localStream);
      });

      // Pull tracks from remote stream, add to video stream
      this.PC.ontrack = (event) => {
        if (!event.streams.length) return;
        event.streams[0].getTracks().forEach((track) => {
          this.remoteStream.addTrack(track);
        });
      };

      let video = document.getElementById("myVideo");
      video.srcObject = this.localStream;

      let otherVideo = document.getElementById("otherVideo");
      otherVideo.srcObject = this.remoteStream;

      //get latest ice candidate and sent to other party
      this.PC.onicecandidate = (event) => {
        if (event.candidate !== null || event.candidate !== undefined) {
          console.log("icecandidate:send", event.candidate);
          axios.post(route("handshake"), {
            senderId: this.user.id,
            reciverId: this.caller.id,
            _token: csrfToken,
            data: JSON.stringify({
              type: "candidate",
              data: event.candidate,
            }),
          });
        }
      };
    },
    async startCall(user) {
      this.caller = user;
      if (user.id === this.user.id) {
        alert("can't call to own");
        return false;
      }
      this.isCallOn = true;
      this.ModelShow = false;
      this.message = "Calling...";

      axios.post(route("handshake"), {
        senderId: this.user.id,
        reciverId: this.caller.id,
        _token: csrfToken,
        data: JSON.stringify({
          type: "incoming-call",
          data: this.user,
        }),
      });
    },

    rejectCall() {
      this.ModelShow = false;
      this.message = null;

      // other party notify
      axios.post(route("handshake"), {
        senderId: this.user.id,
        reciverId: this.caller.id,
        _token: csrfToken,
        data: JSON.stringify({
          type: "reject",
        }),
      });
    },

    async answerCall() {
      this.message = "Connecting...";
      this.isCallOn = true;
      this.ModelShow = false;
      await this.setupCall();

      // Create offer
      const offerDescription = await this.PC.createOffer();
      await this.PC.setLocalDescription(offerDescription);

      axios.post(route("handshake"), {
        senderId: this.user.id,
        reciverId: this.caller.id,
        _token: csrfToken,
        data: JSON.stringify(offerDescription),
      });
    },

    endCall(shouldNotify = false) {
      this.isCallOn = false;
      this.ModelShow = false;
      this.message = null;
      this.duration = null;

      if (this.localStream) {
        this.localStream.getTracks().forEach(function (track) {
          track.stop();
        });
      }
      if (this.remoteStream) {
        this.remoteStream.getTracks().forEach(function (track) {
          track.stop();
        });
      }
      clearInterval(this.interval);
      this.PC.close();

      if (shouldNotify) {
        axios
          .post(route("handshake"), {
            senderId: this.user.id,
            reciverId: this.caller.id,
            _token: csrfToken,
            data: JSON.stringify({
              type: "endCall",
              data: null,
            }),
          })
          .then(() => {
            this.caller = null;
          });
      } else {
        this.caller = null;
      }
    },

    async setOffer(data, offerData) {
      this.message = "Connecting...";

      await this.setupCall();

      const offerDescription = new RTCSessionDescription(offerData);
      await this.PC.setRemoteDescription(
        new RTCSessionDescription(offerDescription)
      );

      // Create answer
      const answerDescription = await this.PC.createAnswer();
      await this.PC.setLocalDescription(answerDescription);

      axios.post(route("handshake"), {
        senderId: this.user.id,
        reciverId: this.caller.id,
        _token: csrfToken,
        data: JSON.stringify(answerDescription),
      });
    },
    async setAnswer(answerData) {
      const answerDescription = new RTCSessionDescription(answerData);
      this.PC.setRemoteDescription(answerDescription);
    },

    setCandidate(candidateData) {
      if (candidateData.data === null || candidateData.data === undefined) {
        return;
      }
      const candidate = new RTCIceCandidate(candidateData.data);
      console.log("icecandidate:received", candidate);
      this.PC.addIceCandidate(candidate).catch((e) =>
        console.log("candidate-error", e)
      );
    },

    setOnlineUsers() {
      Echo.join("online-users")
        .here((res) => {
          this.onlineUsers = res.map((x) => x.id);
        })
        .joining((res) => {
          this.onlineUsers.push(res.id);
        })
        .leaving((res) => {
          this.onlineUsers = this.onlineUsers.filter((x) => x != res.id);
        });
    },

    msToTime(s) {
      // Pad to 2 or 3 digits, default is 2
      var pad = (n, z = 2) => ("00" + n).slice(-z);
      return (
        pad((s / 3.6e6) | 0) +
        ":" +
        pad(((s % 3.6e6) / 6e4) | 0) +
        ":" +
        pad(((s % 6e4) / 1000) | 0)
      );
    },

    handShaking() {
      Echo.channel(`handshake.${this.user.id}`).listen(
        "SendHandShake",
        (data) => {
          const handShakeData = JSON.parse(data.data);

          if (handShakeData.type === "incoming-call") {
            console.log("incoming-call");
            this.caller = handShakeData.data;
            this.ModelShow = true;
            this.message = "Ringing...";

            if (document.hidden) {
              const notification = new Notification(
                `${this.caller.name} is calling you`,
                {
                  body: "Click to answer",
                  icon: "/phone-notification.png",
                }
              );

              notification.onclick = () => {
                this.answerCall();
                window.parent.parent.focus();
                notification.close();
              };
            }

            if (this.isCallOn) {
              axios.post(route("handshake"), {
                senderId: this.user.id,
                reciverId: this.caller.id,
                _token: csrfToken,
                data: JSON.stringify({
                  type: "in-call",
                  data: this.user,
                }),
              });
            } else {
              axios.post(route("handshake"), {
                senderId: this.user.id,
                reciverId: this.caller.id,
                _token: csrfToken,
                data: JSON.stringify({
                  type: "ring",
                  data: this.user,
                }),
              });
            }
          }

          //1 person got when 2nd person answer call
          if (handShakeData.type == "offer") {
            console.log("offer-received");
            this.setOffer(data, handShakeData);
          }

          // 2st person got
          if (handShakeData.type == "answer") {
            console.log("answer-received");
            this.setAnswer(handShakeData);
          }

          //second person got
          if (handShakeData.type == "candidate") {
            console.log("candidate-received");
            this.setCandidate(handShakeData);
          }

          //1st person got
          if (handShakeData.type == "reject") {
            console.log("rejected-received");
            this.message = "Rejected.";
          }

          //anyone can get
          if (handShakeData.type == "endCall") {
            console.log("endCall-received");
            this.endCall();
          }

          //anyone can get
          if (handShakeData.type == "ring") {
            console.log("ring-received");
            this.message = "Ringing...";
          }

          //anyone can get
          if (handShakeData.type == "in-call") {
            console.log("ring-received");
            this.message = "Already in call.";
          }
        }
      );
    },
    shareScreen() {
      navigator.mediaDevices
        .getDisplayMedia({ cursor: true })
        .then((stream) => {
          const screenTrack = stream.getTracks()[0];

          let video = document.getElementById("myVideo");
          video.srcObject = stream;
          this.senderTrack.replaceTrack(screenTrack);

          screenTrack.onended = () => {
            const camVideo = this.localStream
              .getTracks()
              .find((track) => track.kind === "video");

            this.senderTrack.replaceTrack(camVideo);
            video.srcObject = this.localStream;
          };
        });
    },
    keyBind(e) {
      if (e.keyCode === 70) {
        // F button
        this.isFullScreen = !this.isFullScreen;
      }
      if (e.keyCode === 77) {
        // M button
        this.audio = !this.audio;
      }
      if (e.keyCode === 86) {
        // V button
        this.video = !this.video;
      }
      if (e.keyCode === 83) {
        // S button
        this.shareScreen();
      }
    },
    checkState() {
      this.PC.onconnectionstatechange = (d) => {
        console.log("this.PC.iceConnectionState", this.PC.iceConnectionState);
        switch (this.PC.iceConnectionState) {
          case "connected":
            this.message = null;
            this.setDragable();
            const startTime = new Date().getTime();
            const self = this;
            this.interval = setInterval(function () {
              let endTime = new Date().getTime();
              self.duration = endTime - startTime;
            }, 1000);
            break;
          case "disconnected":
          case "failed":
            this.endCall(true);
            if (this.interval) {
              clearInterval(this.interval);
            }
            console.log("disconnected..........");
            break;
          case "closed":
            this.endCall(true);
            break;
        }
      };
      this.PC.onsignalingstatechange = (d) => {
        switch (this.PC.signalingState) {
          case "closed":
            console.log("Signalling state is 'closed'");
            console.log("Other user Left");
            break;
        }
      };
    },
    async createVideoStream(isOnlyAudio = false, isOnlyVideo = false) {
      let constraints = {
        video: {
          width: {
            min: 1280,
            max: 1920,
          },
          height: {
            min: 720,
            max: 1080,
          },
          frameRate: 30,
        },
        audio: {
          noiseSuppression: true,
        },
      };

      if (isOnlyAudio) {
        constraints = {
          video: false,
          audio: {
            noiseSuppression: true,
          },
        };
      }
      if (isOnlyVideo) {
        constraints = {
          video: true,
          audio: false,
        };
      }
      return await navigator.mediaDevices
        .getUserMedia(constraints)
        .then((stream) => {
          return stream;
        })
        .catch((err) => {
          return null;
        });
    },
    setDragable() {
      const padding = 15;
      $("#myVideo").draggable({
        containment: ".calling-container",
        drag: function () {
          $(this).css({
            transition: "none",
          });
        },
        stop: function (e, ui) {
          const xPos = ui.position.left;
          const yPos = ui.position.top;
          const containerHeight = $(".calling-container").height();
          const containerWidth = $(".calling-container").width();
          const middleLineX = containerWidth / 2 - $(this).width() / 2;
          const middleLineY = containerHeight / 2 - $(this).height() / 2;

          if (xPos <= middleLineX && yPos <= middleLineY) {
            $(this).css({
              top: padding,
              left: padding,
              transition: "all 1s ease",
            });
          }

          if (xPos >= middleLineX && yPos <= middleLineY) {
            $(this).css({
              top: padding,
              left: containerWidth - $(this).width() - padding,
              transition: "all 1s ease",
            });
          }

          if (xPos >= middleLineX && yPos >= middleLineY) {
            $(this).css({
              top: containerHeight - $(this).height() - padding,
              left: containerWidth - $(this).width() - padding,
              transition: "all 1s ease",
            });
          }

          if (xPos <= middleLineX && yPos >= middleLineY) {
            $(this).css({
              top: containerHeight - $(this).height() - padding,
              left: padding,
              transition: "all 1s ease",
            });
          }
        },
      });
    },
    exitHandler() {
      if (
        !document.fullscreenElement &&
        !document.webkitIsFullScreen &&
        !document.mozFullScreen &&
        !document.msFullscreenElement
      ) {
        this.isFullScreen = false;
      }
    },
  },

  mounted() {
    // ask to notification permission
    Notification.requestPermission();

    this.PC = new RTCPeerConnection(this.servers);
    this.setOnlineUsers();
    this.handShaking();
    window.addEventListener("keyup", this.keyBind);
    document.addEventListener("fullscreenchange", this.exitHandler);
    document.addEventListener("webkitfullscreenchange", this.exitHandler);
    document.addEventListener("mozfullscreenchange", this.exitHandler);
    document.addEventListener("MSFullscreenChange", this.exitHandler);

    const self = this;
    setInterval(() => {
      self.checkState();
    }, 2000);
  },
  beforeDestroy: function () {
    window.removeEventListener("keyup", 70);
    window.removeEventListener("keyup", 77);
    window.removeEventListener("keyup", 86);
    window.removeEventListener("keyup", 83);
    document.removeEventListener("fullscreenchange", this.exitHandler);
    document.removeEventListener("webkitfullscreenchange", this.exitHandler);
    document.removeEventListener("mozfullscreenchange", this.exitHandler);
    document.removeEventListener("MSFullscreenChange", this.exitHandler);
    Echo.leave("online-users");
    Echo.leave(`handshake.${this.user.id}`);
  },
  watch: {
    message: (val) => {
      const audio = document.getElementById("ringtone");
      if (val === "Ringing...") {
        audio.play();
      } else {
        audio.pause();
        audio.currentTime = 0;
      }
    },
    isCallOn: (val) => {
      const self = this;
      if (val) {
        window.onbeforeunload = function () {
          self.endCall();
          return "are you sure you want to leave ?.";
        };
      } else {
        window.onbeforeunload = function () {
          return null;
        };
      }
    },
    video: async function (val) {
      this.localStream.getVideoTracks()[0].enabled = val;
    },
    audio: function (val) {
      this.localStream.getAudioTracks()[0].enabled = val;
    },
    isFullScreen: function (val) {
      const element = document.querySelector(".target-full-screen");
      if (val) {
        const requestFullScreen =
          element.requestFullscreen ||
          element.mozRequestFullScreen ||
          element.webkitRequestFullscreen ||
          element.msRequestFullscreen;
        requestFullScreen.call(element);
      } else {
        const requestexitFullScreen =
          document.exitFullscreen ||
          document.mozCancelFullScreen ||
          document.webkitExitFullscreen ||
          document.msExitFullscreen;
        requestexitFullScreen.call(document);
        $("#myVideo").removeAttr("style");
      }
    },
  },
};
</script>


<style scoped>
.adjust-margin {
  margin-left: -3px;
}

.indicator.online {
  background: #28b62c;
  display: inline-block;
  position: absolute;
  width: 1em;
  height: 1em;
  border-radius: 50%;
  right: 0px;
  bottom: 0px;
  -webkit-animation: pulse-animation 2s infinite linear;
}

@-webkit-keyframes pulse-animation {
  0% {
    -webkit-transform: scale(1);
  }
  25% {
    -webkit-transform: scale(1);
  }
  50% {
    -webkit-transform: scale(1.2);
  }
  75% {
    -webkit-transform: scale(1);
  }
  100% {
    -webkit-transform: scale(1);
  }
}

.controller {
  width: 100%;
  bottom: 3%;
  justify-content: center;
}

.video-call-actions {
  position: absolute;
  display: flex;
  justify-content: center;
  bottom: 25px;
  left: 0;
  right: 0;
  margin-left: auto;
  margin-right: auto;
  z-index: 12;
}

.video-action-button {
  background-repeat: no-repeat;
  background-size: 24px;
  border: none;
  height: 48px;
  margin: 0 8px;
  border-radius: 8px;
  width: 48px;
  cursor: pointer;
  outline: none;
  background-color: rgb(255 255 255 / 80%);
}

.video-action-button.mic {
  background-image: url("data:image/svg+xml;base64,PHN2ZyB2ZXJzaW9uPSIxLjIiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmlld0JveD0iMCAwIDUxMiA1MTIiIHdpZHRoPSI1MTIiIGhlaWdodD0iNTEyIj4KCTx0aXRsZT5taWNyb3Bob25lLXN2ZzwvdGl0bGU+Cgk8c3R5bGU+CgkJLnMwIHsgZmlsbDogIzAwMDAwMCB9IAoJCS5zMSB7IGZpbGw6ICMwMDAwMDA7c3Ryb2tlOiAjMDAwMDAwO3N0cm9rZS1saW5lY2FwOiByb3VuZDtzdHJva2UtbGluZWpvaW46IHJvdW5kO3N0cm9rZS13aWR0aDogMjMgfSAKCTwvc3R5bGU+Cgk8ZyBpZD0iTGF5ZXIiPgoJCTxwYXRoIGlkPSJMYXllciIgZmlsbC1ydWxlPSJldmVub2RkIiBjbGFzcz0iczAiIGQ9Im0zMDYuMSA0Ni4yYzE2LjQgMTAuMSAzMS44IDI4LjIgMzguOCA0NS45YzYuOSAxNy4yIDYuNiAxMy4xIDYuNiA5OS45YzAgODYuMSAwLjIgODIuNy02LjEgOTguOWMtOS45IDI0LjktMzEuNSA0Ni4xLTU2LjUgNTUuMmMtMjIuNyA4LjMtNDguMyA3LjYtNzAuOC0yYy0yMi4yLTkuNC00Mi4xLTI5LjgtNTEtNTIuMmMtNi45LTE3LjItNi42LTEzLjEtNi42LTk5LjljMC03MS43IDAuMi03OS4xIDEuOC04NS40YzQuNi0xOC4yIDEyLjgtMzIuNyAyNS43LTQ1LjdjMTQuOC0xNSAzMS4yLTIzLjggNTEuMy0yNy40YzIyLjMtNC4xIDQ3LjIgMC43IDY2LjggMTIuN3ptLTEwNy4xIDUzLjJjLTcgMTQtNyAxMy41LTcgOTIuNmMwIDc5LjMgMCA3OC42IDcuMiA5M2M3LjMgMTQuNiAyMC40IDI1LjggMzcgMzEuOGM2LjMgMi4yIDguOSAyLjYgMTkuMyAyLjdjOS43IDAgMTMuMy0wLjQgMTktMi4yYzE5LjktNi4zIDM2LjUtMjIuOSA0Mi44LTQyLjhjMi4yLTcgMi4yLTcuMyAyLjItODIuNWMwLTc1LjIgMC03NS41LTIuMi04Mi41Yy02LjktMjEuNy0yNi4yLTM5LjUtNDcuOC00NGMtMjguNy02LTU3LjMgNy44LTcwLjUgMzMuOXoiIC8+CgkJPHBhdGggaWQ9IkxheWVyIiBjbGFzcz0iczAiIGQ9Im0xMjAuOCAyMjYuN2M2LjQgNC44IDYuNiA1LjcgNy40IDI3LjFjMC43IDIxLjMgMi4xIDMwLjIgNyA0NC4yYzYuNiAxOC44IDE2IDMzLjUgMzAuNyA0OC4xYzE4LjMgMTguNCAzNy42IDI5IDYzLjUgMzUuMWMxMi40IDIuOSAzOSAzLjEgNTEuNiAwLjRjMjUuNy01LjYgNDYuNi0xNi45IDY1LjEtMzUuNWMxNC43LTE0LjYgMjQuMS0yOS4zIDMwLjctNDguMWM0LjktMTQgNi4zLTIyLjkgNy00NC4yYzAuNi0xNy43IDAuOS0xOS45IDIuOC0yMi40YzMuOS01LjMgNy4xLTYuOSAxMy40LTYuOWM2LjMgMCA5LjUgMS42IDEzLjQgNi45YzIgMi42IDIuMSA0LjEgMi4xIDIyLjRjLTAuMSAyMi45LTEuNSAzMy03LjYgNTEuNWMtMTguNSA1Ni42LTY3LjEgOTguNy0xMjUuMSAxMDguNGwtMTAuOCAxLjd2MTYuM3YxNi4ybDE4LjkgMC4zYzE3LjMgMC4zIDE5LjIgMC41IDIxLjcgMi40YzUuMyAzLjkgNi45IDcuMSA2LjkgMTMuNGMwIDYuMy0xLjYgOS41LTYuOSAxMy40Yy0yLjcgMi4xLTMuOCAyLjEtNTYuNiAyLjFjLTUyLjggMC01My45IDAtNTYuNi0yLjFjLTUuMy0zLjktNi45LTcuMS02LjktMTMuNGMwLTYuMyAxLjYtOS41IDYuOS0xMy40YzIuNS0xLjkgNC40LTIuMSAyMS43LTIuNGwxOC45LTAuM3YtMTYuMnYtMTYuM2wtMTAuNy0xLjhjLTYtMS0xNi4xLTMuNS0yMi41LTUuNmMtNTUuNi0xOC4zLTk2LjItNjQtMTA3LjgtMTIxLjZjLTIuMi0xMC42LTMuOS00My0yLjYtNDkuN2MwLjgtNC40IDQuOS05LjUgOS4xLTExLjNjNC0xLjkgMTItMS4yIDE1LjMgMS4zeiIgLz4KCQk8cGF0aCBpZD0iU2hhcGUgMSIgY2xhc3M9InMxIiBkPSJtNDI4LjcgMzUuNGwzLjUgMy4ybC0zNjIuOSAzOTdsLTMuNC0zLjJ6IiAvPgoJPC9nPgo8L3N2Zz4=");
  background-position: center;
}

.video-action-button.mic-active {
  background-image: url("data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBzdGFuZGFsb25lPSJubyI/Pgo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDIwMDEwOTA0Ly9FTiIKICJodHRwOi8vd3d3LnczLm9yZy9UUi8yMDAxL1JFQy1TVkctMjAwMTA5MDQvRFREL3N2ZzEwLmR0ZCI+CjxzdmcgdmVyc2lvbj0iMS4wIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciCiB3aWR0aD0iNTEyLjAwMDAwMHB0IiBoZWlnaHQ9IjUxMi4wMDAwMDBwdCIgdmlld0JveD0iMCAwIDUxMi4wMDAwMDAgNTEyLjAwMDAwMCIKIHByZXNlcnZlQXNwZWN0UmF0aW89InhNaWRZTWlkIG1lZXQiPgoKPGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMC4wMDAwMDAsNTEyLjAwMDAwMCkgc2NhbGUoMC4xMDAwMDAsLTAuMTAwMDAwKSIKZmlsbD0iIzAwMDAwMCIgc3Ryb2tlPSJub25lIj4KPHBhdGggZD0iTTIzOTMgNDc4NSBjLTIwMSAtMzYgLTM2NSAtMTI0IC01MTMgLTI3NCAtMTI5IC0xMzAgLTIxMSAtMjc1IC0yNTcKLTQ1NyAtMTYgLTYzIC0xOCAtMTM3IC0xOCAtODU0IDAgLTg2OCAtMyAtODI3IDY2IC05OTkgODkgLTIyNCAyODggLTQyOCA1MTAKLTUyMiAyMjUgLTk2IDQ4MSAtMTAzIDcwOCAtMjAgMjUwIDkxIDQ2NiAzMDMgNTY1IDU1MiA2MyAxNjIgNjEgMTI4IDYxIDk4OSAwCjg2OCAzIDgyNyAtNjYgOTk5IC03MCAxNzcgLTIyNCAzNTggLTM4OCA0NTkgLTE5NiAxMjAgLTQ0NSAxNjggLTY2OCAxMjd6Cm0zMDIgLTMyMCBjMjE2IC00NSA0MDkgLTIyMyA0NzggLTQ0MCAyMiAtNzAgMjIgLTczIDIyIC04MjUgMCAtNzUyIDAgLTc1NQotMjIgLTgyNSAtNjMgLTE5OSAtMjI5IC0zNjUgLTQyOCAtNDI4IC01NyAtMTggLTkzIC0yMiAtMTkwIC0yMiAtMTA0IDEgLTEzMAo1IC0xOTMgMjcgLTE2NiA2MCAtMjk3IDE3MiAtMzcwIDMxOCAtNzIgMTQ0IC03MiAxMzcgLTcyIDkzMCAwIDc5MSAwIDc4NiA3MAo5MjYgMTMyIDI2MSA0MTggMzk5IDcwNSAzMzl6Ii8+CjxwYXRoIGQ9Ik0xMDU1IDI4NjYgYy00MiAtMTggLTgzIC02OSAtOTEgLTExMyAtMTMgLTY3IDQgLTM5MSAyNiAtNDk3IDExNgotNTc2IDUyMiAtMTAzMyAxMDc4IC0xMjE2IDY0IC0yMSAxNjUgLTQ2IDIyNSAtNTYgbDEwNyAtMTggMCAtMTYzIDAgLTE2MgotMTg5IC0zIGMtMTczIC0zIC0xOTIgLTUgLTIxNyAtMjQgLTUzIC0zOSAtNjkgLTcxIC02OSAtMTM0IDAgLTYzIDE2IC05NSA2OQotMTM0IDI3IC0yMSAzOCAtMjEgNTY2IC0yMSA1MjggMCA1MzkgMCA1NjYgMjEgNTMgMzkgNjkgNzEgNjkgMTM0IDAgNjMgLTE2Cjk1IC02OSAxMzQgLTI1IDE5IC00NCAyMSAtMjE3IDI0IGwtMTg5IDMgMCAxNjIgMCAxNjMgMTA4IDE3IGM1ODAgOTcgMTA2Ngo1MTggMTI1MSAxMDg0IDYxIDE4NSA3NSAyODYgNzYgNTE1IDAgMTgzIC0xIDE5OCAtMjEgMjI0IC0zOSA1MyAtNzEgNjkgLTEzNAo2OSAtNjMgMCAtOTUgLTE2IC0xMzQgLTY5IC0xOSAtMjUgLTIyIC00NyAtMjggLTIyNCAtNyAtMjEzIC0yMSAtMzAyIC03MAotNDQyIC02NiAtMTg4IC0xNjAgLTMzNSAtMzA3IC00ODEgLTE4NSAtMTg2IC0zOTQgLTI5OSAtNjUxIC0zNTUgLTEyNiAtMjcKLTM5MiAtMjUgLTUxNiA0IC0yNTkgNjEgLTQ1MiAxNjcgLTYzNSAzNTEgLTE0NyAxNDYgLTI0MSAyOTMgLTMwNyA0ODEgLTQ5CjE0MCAtNjMgMjI5IC03MCA0NDIgLTggMjE0IC0xMCAyMjMgLTc0IDI3MSAtMzMgMjUgLTExMyAzMiAtMTUzIDEzeiIvPgo8L2c+Cjwvc3ZnPgo=");
  background-position: center;
}

.video-action-button.camera {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' stroke='%232c303a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-camera-off' viewBox='0 0 24 24'%3E%3Cpath d='M1 1l22 22M21 21H3a2 2 0 01-2-2V8a2 2 0 012-2h3m3-3h6l2 3h4a2 2 0 012 2v9.34m-7.72-2.06a4 4 0 11-5.56-5.56'/%3E%3C/svg%3E%0A");
  background-position: center;
}

.video-action-button.camera-active {
  background-image: url("data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBzdGFuZGFsb25lPSJubyI/Pgo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDIwMDEwOTA0Ly9FTiIKICJodHRwOi8vd3d3LnczLm9yZy9UUi8yMDAxL1JFQy1TVkctMjAwMTA5MDQvRFREL3N2ZzEwLmR0ZCI+CjxzdmcgdmVyc2lvbj0iMS4wIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciCiB3aWR0aD0iNTEyLjAwMDAwMHB0IiBoZWlnaHQ9IjUxMi4wMDAwMDBwdCIgdmlld0JveD0iMCAwIDUxMi4wMDAwMDAgNTEyLjAwMDAwMCIKIHByZXNlcnZlQXNwZWN0UmF0aW89InhNaWRZTWlkIG1lZXQiPgoKPGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMC4wMDAwMDAsNTEyLjAwMDAwMCkgc2NhbGUoMC4xMDAwMDAsLTAuMTAwMDAwKSIKZmlsbD0iIzAwMDAwMCIgc3Ryb2tlPSJub25lIj4KPHBhdGggZD0iTTM5MiA0NjEwIGMtNDUgLTI4IC03NiAtOTggLTY3IC0xNTEgOSAtNTMgNTggLTEwNCAxMTQgLTExOSAyOSAtNwoxMDYgLTEwIDIyMCAtOCAxNjIgMyAxNzkgNSAyMTIgMjYgODYgNTMgOTIgMTY5IDE0IDI0MiAtMjYgMjUgLTI4IDI1IC0yNDMgMjgKLTIwNCAyIC0yMTkgMSAtMjUwIC0xOHoiLz4KPHBhdGggZD0iTTE3MjkgNDYwNiBjLTM2IC0xMyAtODYgLTM3IC0xMTAgLTU0IC0yNCAtMTcgLTE1MiAtMTM5IC0yODQgLTI3MgotMTMyIC0xMzMgLTI1MyAtMjQ5IC0yNzAgLTI1OCAtMjYgLTE1IC03NiAtMTggLTM2MCAtMjIgLTMxNyAtNiAtMzMyIC03IC0zOTAKLTI5IC04MSAtMzIgLTEyMyAtNjAgLTE4MiAtMTIwIC01NiAtNTcgLTEwNSAtMTUwIC0xMjIgLTIyOSAtOCAtMzcgLTExIC00NTQKLTExIC0xMzg1IDAgLTExNDkgMiAtMTM0MSAxNSAtMTM5MCA0MiAtMTYzIDE2MSAtMjkwIDMxNiAtMzM4IDU5IC0xOCAxMjkgLTE5CjIyMjkgLTE5IDIxMDAgMCAyMTcwIDEgMjIyOSAxOSAxNTUgNDggMjc0IDE3NSAzMTYgMzM4IDIzIDg3IDIyIDI2OTYgMCAyNzkyCi00MiAxNzcgLTE5MCAzMTcgLTM2OSAzNTAgLTM0IDYgLTE4NCAxMSAtMzQ4IDExIC0yNDUgMCAtMjk0IDIgLTMyMSAxNiAtMTggOQotMTQ5IDEzMiAtMjkyIDI3NCAtMTQzIDE0MSAtMjgwIDI2OSAtMzA1IDI4MyAtMTAzIDU4IC04NiA1NyAtOTE0IDU3IGwtNzYxIDAKLTY2IC0yNHogbTE1NzggLTI4NyBjMTUgLTYgMTQ3IC0xMjkgMjkzIC0yNzQgMzYxIC0zNTggMzMyIC0zNDUgNzY5IC0zNDUgMTY0CjAgMzEyIC01IDMzMSAtMTAgNDEgLTExIDk0IC02MSAxMDkgLTEwMyA4IC0xOSAxMSAtNDQwIDExIC0xMzQ1IDAgLTEyNjMgLTEKLTEzMTkgLTE5IC0xMzU4IC0xMCAtMjMgLTM0IC01MiAtNTIgLTY1IGwtMzQgLTI0IC0yMTU2IDAgLTIxNTYgMCAtMzUgMjcKYy03MyA1NiAtNjkgLTM5IC02NiAxNDM5IGwzIDEzMjYgMjkgMzcgYzU1IDcyIDcyIDc2IDQxMSA3NiAzMTggMCAzNTUgNCA0NTIKNTEgMzUgMTYgMTIxIDk1IDMxMyAyODUgMTQ2IDE0NSAyNzkgMjcwIDI5NSAyNzggMjYgMTQgMTE5IDE2IDc1MiAxNiA0ODcgMAo3MzEgLTMgNzUwIC0xMXoiLz4KPHBhdGggZD0iTTI0MzIgMzUwOSBjLTQ1MCAtNTIgLTgyOSAtMzgyIC05NDkgLTgyNCAtMjUgLTkyIC0yNyAtMTE1IC0yNyAtMjg1CjEgLTIwMyAxMCAtMjU3IDc0IC00MTcgMTI4IC0zMTggNDE1IC01NjkgNzU3IC02NjAgODYgLTIzIDExOCAtMjYgMjY4IC0yNwoxNDUgLTEgMTgzIDMgMjU4IDIyIDUxMSAxMjkgODU2IDU2NCA4NTcgMTA3OCAwIDIwMyAtMzUgMzQ3IC0xMjYgNTI0IC0xNDAKMjcxIC00MDcgNDg0IC03MDIgNTYwIC0xNDMgMzYgLTI3MSA0NSAtNDEwIDI5eiBtMzM0IC0zMTkgYzIyMCAtNTcgNDE4IC0yMTgKNTIwIC00MjUgNjEgLTEyNSA4NCAtMjIyIDg0IC0zNTkgMCAtMjIxIC03MiAtNDA1IC0yMTkgLTU1OSAtMTQ4IC0xNTcgLTMzNQotMjQzIC01NTEgLTI1NCAtMzYzIC0xOSAtNjkwIDIwNSAtODEyIDU1NyAtMzEgOTEgLTMyIDEwMiAtMzMgMjQ1IDAgMTY5IDExCjIyNCA3MCAzNTMgOTQgMjAyIDI3NCAzNTkgNDk3IDQzMSAxMzMgNDQgMjk5IDQ4IDQ0NCAxMXoiLz4KPC9nPgo8L3N2Zz4K");
  background-position: center;
}

.video-action-button.full-screen {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' stroke='%232c303a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-maximize' viewBox='0 0 24 24'%3E%3Cpath d='M8 3H5a2 2 0 00-2 2v3m18 0V5a2 2 0 00-2-2h-3m0 18h3a2 2 0 002-2v-3M3 16v3a2 2 0 002 2h3'/%3E%3C/svg%3E%0A");
  background-position: center;
}

.video-action-button.full-screen-active {
  background-image: url("data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBzdGFuZGFsb25lPSJubyI/Pgo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDIwMDEwOTA0Ly9FTiIKICJodHRwOi8vd3d3LnczLm9yZy9UUi8yMDAxL1JFQy1TVkctMjAwMTA5MDQvRFREL3N2ZzEwLmR0ZCI+CjxzdmcgdmVyc2lvbj0iMS4wIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciCiB3aWR0aD0iNTEyLjAwMDAwMHB0IiBoZWlnaHQ9IjUxMi4wMDAwMDBwdCIgdmlld0JveD0iMCAwIDUxMi4wMDAwMDAgNTEyLjAwMDAwMCIKIHByZXNlcnZlQXNwZWN0UmF0aW89InhNaWRZTWlkIG1lZXQiPgoKPGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMC4wMDAwMDAsNTEyLjAwMDAwMCkgc2NhbGUoMC4xMDAwMDAsLTAuMTAwMDAwKSIKZmlsbD0iIzAwMDAwMCIgc3Ryb2tlPSJub25lIj4KPHBhdGggZD0iTTgwMCAzNzYwIGwwIC01NjAgMTYwIDAgMTYwIDAgMCAyODcgMCAyODggNTAzIC01MDMgNTAyIC01MDIgMTEzCjExMiAxMTIgMTEzIC01MDIgNTAyIC01MDMgNTAzIDI4OCAwIDI4NyAwIDAgMTYwIDAgMTYwIC01NjAgMCAtNTYwIDAgMCAtNTYweiIvPgo8cGF0aCBkPSJNMzIwMCA0MTYwIGwwIC0xNjAgMjg3IDAgMjg4IDAgLTUwMyAtNTAzIC01MDIgLTUwMiAxMTIgLTExMyAxMTMKLTExMiA1MDIgNTAyIDUwMyA1MDMgMCAtMjg4IDAgLTI4NyAxNjAgMCAxNjAgMCAwIDU2MCAwIDU2MCAtNTYwIDAgLTU2MCAwIDAKLTE2MHoiLz4KPHBhdGggZD0iTTE2MjIgMTg0OCBsLTUwMiAtNTAzIDAgMjg4IDAgMjg3IC0xNjAgMCAtMTYwIDAgMCAtNTYwIDAgLTU2MCA1NjAKMCA1NjAgMCAwIDE2MCAwIDE2MCAtMjg3IDAgLTI4OCAwIDUwNSA1MDUgNTA1IDUwNSAtMTEwIDExMCBjLTYwIDYwIC0xMTIgMTEwCi0xMTUgMTEwIC0zIDAgLTIzMSAtMjI2IC01MDggLTUwMnoiLz4KPHBhdGggZD0iTTI4NzUgMjI0MCBsLTExMCAtMTEwIDUwNSAtNTA1IDUwNSAtNTA1IC0yODggMCAtMjg3IDAgMCAtMTYwIDAKLTE2MCA1NjAgMCA1NjAgMCAwIDU2MCAwIDU2MCAtMTYwIDAgLTE2MCAwIDAgLTI4NyAwIC0yODggLTUwMyA1MDMgYy0yNzYgMjc2Ci01MDQgNTAyIC01MDcgNTAyIC0zIDAgLTU1IC01MCAtMTE1IC0xMTB6Ii8+CjwvZz4KPC9zdmc+Cg==");
  background-position: center;
}

.video-action-button.endcall {
  color: #ff1932;
  width: auto;
  font-size: 14px;
  padding-left: 42px;
  padding-right: 12px;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ff1932' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-phone-missed'%3E%3Cline x1='23' y1='1' x2='17' y2='7'/%3E%3Cline x1='17' y1='1' x2='23' y2='7'/%3E%3Cpath d='M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z'/%3E%3C/svg%3E");
  background-size: 20px;
  background-position: center left 12px;
}

.video-action-button.screen {
  background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0Ij48cGF0aCBmaWxsPSJub25lIiBkPSJNMCAwaDI0djI0SDBWMHoiLz48cGF0aCBkPSJNMjAgMThjMS4xIDAgMS45OS0uOSAxLjk5LTJMMjIgNmMwLTEuMTEtLjktMi0yLTJINGMtMS4xMSAwLTIgLjg5LTIgMnYxMGMwIDEuMS44OSAyIDIgMkgwdjJoMjR2LTJoLTR6TTQgMTZWNmgxNnYxMC4wMUw0IDE2em05LTYuODdjLTMuODkuNTQtNS40NCAzLjItNiA1Ljg3IDEuMzktMS44NyAzLjIyLTIuNzIgNi0yLjcydjIuMTlsNC0zLjc0TDEzIDd2Mi4xM3oiLz48L3N2Zz4=");
  background-position: center;
}

.duration {
  bottom: 80px;
}

.duration h3 {
  border-radius: 8px;
  background-color: rgb(255 255 255 / 80%);
}

.calling-container {
  width: 100%;
}

.w-100 {
  width: 100%;
}

button:disabled,
button[disabled] {
  background-color: #cccccc;
}
</style>


