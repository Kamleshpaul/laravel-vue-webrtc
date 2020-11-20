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
								class="bg-gray-500 h-16 w-16 m-4 rounded-full text-white relative cursor-pointer"
								@click="startCall(row.id)"
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
						@click="AnswerCall(caller)"
						class="bg-green-300 hover:bg-green-400 text-green-800 font-bold py-2 px-4 rounded inline-flex items-center text-white m-3"
					>
						Answer
					</button>
					<button
						@click="rejectCall"
						class="bg-red-300 hover:bg-red-400 text-red-800 font-bold py-2 px-4 rounded inline-flex items-center text-white m-3"
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
						class="w-1/2"
					></video>
					<video
						id="otherVideo"
						playsinline
						autoplay
						class="w-1/2"
					></video>
				</div>

				<button
					@click="EndCall"
					class="bg-red-300 hover:bg-red-400 text-red-800 font-bold py-2 px-4 rounded inline-flex items-center text-white m-3"
				>
					Close
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
			caller: null,
			ModelShow: false,
			isCallOn: false,
			offerData: null,
			localConnection: null,
			remoteConnection: null,
			offer: null,
		};
	},
	components: {
		AppLayout,
		Modal,
	},

	methods: {
		startCall(id) {
			if (id == this.user.id) {
				alert("can't call to own");
				return false;
			}
			this.isCallOn = true;
			this.ModelShow = false;

			this.localConnection.onicecandidate = (e) => {
				this.offer = JSON.stringify(
					this.localConnection.localDescription
				);
			};
			setTimeout(() => {
				console.log("send offer");
				axios.post(route("handshake"), {
					senderId: this.user.id,
					reciverId: id,
					_token: csrfToken,
					data: this.offer,
				});
			}, 1000);

			this.localConnection
				.createOffer()
				.then((o) => this.localConnection.setLocalDescription(o));
		},

		rejectCall() {
			this.ModelShow = false;
		},

		createRTCOffer(offer, reciverId) {
			this.isCallOn = true;
			this.ModelShow = false;

			this.remoteConnection
				.setRemoteDescription(offer)
				.then((a) => console.log("remote offer accept"));

			//create answer
			this.remoteConnection
				.createAnswer()
				.then((a) => this.remoteConnection.setLocalDescription(a))
				.then((a) => {
					console.log("sent answer");
					axios.post(route("handshake"), {
						senderId: this.user.id,
						reciverId: reciverId,
						_token: csrfToken,
						data: JSON.stringify(
							this.remoteConnection.localDescription
						),
					});
				});
		},

		AnswerCall(user) {},
		EndCall() {
			this.isCallOn = false;
			this.localConnection.close();
			this.remoteConnection.close();
		},

		init() {
			this.localConnection = new RTCPeerConnection();
			this.remoteConnection = new RTCPeerConnection();
			window.remoteConnection = this.remoteConnection;
			// listen for remote video
			this.remoteConnection.ontrack = (e) => {
				console.log("peer1 reciving video");
				let video = document.getElementById("otherVideo");
				video.srcObject = e.streams[0];
			};
			// set track to local video
			navigator.mediaDevices
				.getUserMedia({ video: true, audio: true })
				.then((stream) => {
					let video = document.getElementById("myVideo");
					video.srcObject = stream;

					stream
						.getTracks()
						.forEach((track) =>
							this.localConnection.addTrack(track, stream)
						);
				});
		},
	},

	mounted() {
		this.init();
		Echo.channel(`handshake.${this.user.id}`).listen(
			"SendHandShake",
			(e) => {
				const data = JSON.parse(e.data);
				if (data.type == "offer") {
					const offer = data;
					this.createRTCOffer(offer, e.senderId);
				} else {
					const answer = data;
					console.log("accept  answer");
					this.localConnection
						.setRemoteDescription(answer)
						.then((a) => console.log("final done"));
				}
			}
		);
	},
};
</script>


<style  scoped>
.adjust-margin {
	margin-left: -3px;
}
</style>