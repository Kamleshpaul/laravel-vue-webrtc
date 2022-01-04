<template>
  <app-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Laravel Vue WebRTC
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-5">
            <h1>Users</h1>
            <ul class="flex p-2">
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
                "
                @click="startCall(row)"
              >
                <span
                  class="absolute top-3 left-6 font-bold text-3xl adjust-margin"
                  >{{ row.name.charAt(0) }}
                </span>
                <span
                  class="indicator online"
                  v-show="onlineUsers.filter((x) => row.id === x).length"
                ></span>
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
      <div class="p-5">
        <div class="flex calling-container relative">
          <video
            id="otherVideo"
            v-show="!message"
            playsinline
            class="relative rounded"
            autoplay
            muted
          ></video>
          <video
            v-show="!message"
            id="myVideo"
            playsinline
            autoplay
            class="bg-green absolute bottom-5 right-5 z-20 rounded h-1/5"
          ></video>
          <div v-show="message">
            <p class="p-3">{{ message }}</p>
          </div>
        </div>

        <div class="absolute flex controller">
          <img
            v-if="isCallOn"
            @click="shareScreen"
            src="/share.svg"
            class="h-10 w-10 cursor-pointer mr-2"
            alt="mute"
          />
          <img
            v-if="!audio && isCallOn"
            @click="toggleAudio(true)"
            src="/mic_on.svg"
            class="h-10 w-10 cursor-pointer mr-2"
            alt="mute"
          />
          <img
            v-if="audio && isCallOn"
            @click="toggleAudio(false)"
            src="/mic_off.svg"
            class="h-10 w-10 cursor-pointer mr-2"
            alt="mute"
          />
          <img
            @click="endCall(true)"
            src="/end-call.svg"
            class="h-10 w-10 cursor-pointer mr-2"
            alt="end call"
          />
          <img
            v-if="!video && isCallOn"
            @click="toggleVideo(true)"
            src="/camera_on.svg"
            class="h-10 w-10 cursor-pointer"
            alt="end call"
          />
          <img
            v-if="video && isCallOn"
            @click="toggleVideo(false)"
            src="/camera_off.svg"
            class="h-10 w-10 cursor-pointer"
            alt="end call"
          />
        </div>

        <i
          class="
            bi bi-arrows-fullscreen
            absolute
            top-1
            right-1
            shadow-xl
            cursor-pointer
          "
          @click="fullScreen()"
        ></i>
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
          {
            urls: [
              "stun:stun1.l.google.com:19302",
              "stun:stun2.l.google.com:19302",
            ],
          },
        ],
        iceCandidatePoolSize: 10,
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
    };
  },
  components: {
    AppLayout,
    Modal,
  },

  methods: {
    async setupCall() {
      const conState = this.PC.connectionState;
      if (conState == "closed") {
        this.PC = new RTCPeerConnection(this.servers);
      }

      try {
        this.localStream = await navigator.mediaDevices.getUserMedia({
          video: true,
          audio: true,
        });
      } catch (error) {
        alert("Web cam access denied.");
      }
      this.remoteStream = new MediaStream();

      // Push tracks from local stream to peer connection
      this.localStream.getTracks().forEach((track) => {
        this.senderTrack = this.PC.addTrack(track, this.localStream);
      });

      // Pull tracks from remote stream, add to video stream
      this.PC.ontrack = (event) => {
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
        if (event.candidate) {
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
      this.message = "Ringing...";
      if (user.id == this.user.id) {
        alert("can't call to own");
        return false;
      }
      this.isCallOn = true;
      this.ModelShow = false;

      await this.setupCall();

      // Create offer
      const offerDescription = await this.PC.createOffer();
      await this.PC.setLocalDescription(offerDescription);

      const offer = {
        sdp: offerDescription.sdp,
        type: offerDescription.type,
      };
      axios.post(route("handshake"), {
        senderId: this.user.id,
        reciverId: this.caller.id,
        _token: csrfToken,
        data: JSON.stringify(offer),
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
      this.message = null;
      this.isCallOn = true;
      this.ModelShow = false;

      await this.setupCall();

      // set offer as local and generate answer
      const offerDescription = this.offerData;
      await this.PC.setRemoteDescription(
        new RTCSessionDescription(offerDescription)
      );
      const answerDescription = await this.PC.createAnswer();
      await this.PC.setLocalDescription(answerDescription);
      const answer = {
        type: answerDescription.type,
        sdp: answerDescription.sdp,
      };
      axios.post(route("handshake"), {
        senderId: this.user.id,
        reciverId: this.caller.id,
        _token: csrfToken,
        data: JSON.stringify(answer),
      });
    },

    endCall(shouldNotify = false) {
      this.isCallOn = false;
      this.ModelShow = false;
      this.message = null;

      this.localStream.getTracks().forEach(function (track) {
        track.stop();
      });
      this.remoteStream.getTracks().forEach(function (track) {
        track.stop();
      });
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

    setOffer(data, offerData) {
      this.caller = data.caller;
      this.offerData = offerData;
      this.ModelShow = true;
    },
    async setAnswer(answerData) {
      this.message = null;
      const answerDescription = new RTCSessionDescription(answerData);
      this.PC.setRemoteDescription(answerDescription);
    },
    setCandidate(candidateData) {
      try {
        this.PC.addIceCandidate(new RTCIceCandidate(candidateData.data));
      } catch (error) {}
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

    handShakeing() {
      Echo.channel(`handshake.${this.user.id}`).listen(
        "SendHandShake",
        (data) => {
          const handShakeData = JSON.parse(data.data);

          //second person got
          if (handShakeData.type == "offer") {
            this.setOffer(data, handShakeData);
          }

          // 1st person got
          if (handShakeData.type == "answer") {
            this.setAnswer(handShakeData);
          }

          //second person got
          if (handShakeData.type == "candidate") {
            this.setCandidate(handShakeData);
          }

          //1st person got
          if (handShakeData.type == "reject") {
            this.message = "Rejected.";
          }

          //anyone can get
          if (handShakeData.type == "endCall") {
            this.endCall();
          }
        }
      );
    },
    fullScreen() {
      const element = document.querySelector(".calling-container");
      element.requestFullscreen();
    },
    toggleVideo(status) {
      this.video = status;
      const videoTrack = this.localStream
        .getTracks()
        .find((track) => track.kind === "video");

      videoTrack.enabled = status;
    },
    toggleAudio(status) {
      this.audio = status;

      const videoTrack = this.localStream
        .getTracks()
        .find((track) => track.kind === "audio");

      videoTrack.enabled = status;
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
  },

  mounted() {
    this.PC = new RTCPeerConnection(this.servers);
    this.setOnlineUsers();
    this.handShakeing();
  },
  watch: {
    message: (val) => {
      if (val == "Ringing...") {
        // this.playRingTone();
      } else {
        // this.stopRingTone();
      }
    },
    isCallOn: (val) => {
      if (val) {
        window.onbeforeunload = function () {
          return "are you sure you want to leave ?.";
        };
      }
    },
  },
};
</script>


<style  scoped>
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
  left: 35%;
  bottom: 3%;
}
@media only screen and (max-width: 780px) {
  .controller {
    left: 30%;
  }
}
</style>
