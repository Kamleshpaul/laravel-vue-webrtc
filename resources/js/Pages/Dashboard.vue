<template>
  <app-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Laravel Vue WebRTC
      </h2>
    </template>

    <audio hidden id="ringtone" src="/skype_phone.mp3"></audio>
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
                  >{{ row.name.charAt(0) }}</span
                >
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
        <h2>Video Chat</h2>
        <div class="flex">
          <video
            id="myVideo"
            playsinline
            autoplay
            muted
            class="w-1/2 mr-2"
          ></video>
          <video
            v-show="!message"
            id="otherVideo"
            playsinline
            muted
            autoplay
            class="w-1/2"
          ></video>
          <div v-show="message" class="w-1/2">
            <p class="p-3">{{ message }}</p>
          </div>
        </div>

        <button
          @click="endCall(true)"
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
          {{ isCallOn ? "End Call" : "Close" }}
        </button>
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
        this.PC.addTrack(track, this.localStream);
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
  },

  mounted() {
    this.PC = new RTCPeerConnection(this.servers);

    Echo.channel(`handshake.${this.user.id}`).listen(
      "SendHandShake",
      (data) => {
        const handShakeData = JSON.parse(data.data);
        if (handShakeData.type == "offer") {
          this.setOffer(data, handShakeData);
        }

        if (handShakeData.type == "answer") {
          this.setAnswer(handShakeData);
        }
        if (handShakeData.type == "candidate") {
          this.setCandidate(handShakeData);
        }

        if (handShakeData.type == "reject") {
          this.message = "Rejected.";
        }

        if (handShakeData.type == "endCall") {
          this.endCall();
        }
      }
    );
  },
  watch: {
    message: (val) => {
      const audio = document.getElementById("ringtone");
      if (val == "Ringing...") {
        audio.play();
      } else {
        audio.pause();
        audio.currentTime = 0;
      }
    },
  },
};
</script>


<style  scoped>
.adjust-margin {
  margin-left: -3px;
}
</style>